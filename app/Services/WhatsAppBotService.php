<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    protected WahaService $waha;

    public function __construct(WahaService $waha)
    {
        $this->waha = $waha;
    }

    /**
     * Proses pesan teks yang masuk dari webhook WAHA.
     */
    public function handleIncomingMessage(string $fromChatId, string $messageText): void
    {
        $phone = WahaService::extractPhoneNumber($fromChatId);
        $text = trim($messageText);

        if ($text === '') {
            return;
        }

        // 1. Verifikasi apakah nomor pengirim terdaftar sebagai karyawan
        $user = $this->findUserByPhone($phone);

        if (! $user) {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ *Nomor Tidak Terdaftar*\n\n"
                . "Nomor WhatsApp Anda ({$phone}) belum terdaftar di sistem *TURIMA FRAM*.\n\n"
                . "Silakan hubungi Manajer / Admin untuk mendaftarkan nomor WhatsApp Anda pada data karyawan."
            );
            return;
        }

        // 2. Ambil atau buat sesi percakapan
        $session = WhatsAppSession::firstOrCreate(
            ['phone_number' => $phone],
            [
                'user_id' => $user->id,
                'current_step' => 'MENU',
                'last_active_at' => Carbon::now(),
            ]
        );

        // Pastikan user_id tersinkron
        if ($session->user_id !== $user->id) {
            $session->user_id = $user->id;
            $session->save();
        }

        // 3. Tangani perintah global (Batal / Menu / Halo)
        $lowerText = strtolower($text);

        if (in_array($lowerText, ['batal', 'cancel', 'reset'])) {
            $session->resetToMenu();
            $this->waha->sendMessage(
                $fromChatId,
                "❌ *Aksi Dibatalkan*\n\n"
                . "Percakapan telah direset. Ketik *menu* untuk melihat opsi yang tersedia."
            );
            return;
        }

        if (in_array($lowerText, ['menu', 'halo', 'hai', 'hi', 'p', 'help', 'bantuan'])) {
            $session->resetToMenu();
            $this->sendMainMenu($fromChatId, $user);
            return;
        }

        // 4. Deteksi timeout sesi (jika sudah lebih dari 15 menit dan sedang di tengah alur)
        if ($session->current_step !== 'MENU' && $session->isExpired(config('waha.session_timeout_minutes', 15))) {
            $session->resetToMenu();
            $this->waha->sendMessage(
                $fromChatId,
                "⏰ *Sesi Berakhir (Timeout)*\n\n"
                . "Sesi input tugas Anda sebelumnya telah kedaluwarsa karena tidak ada aktivitas."
            );
            $this->sendMainMenu($fromChatId, $user);
            return;
        }

        // Update timestamp interaksi terakhir
        $session->last_active_at = Carbon::now();
        $session->save();

        // 5. Jalankan state machine percakapan
        switch ($session->current_step) {
            case 'MENU':
                $this->handleMenuStep($fromChatId, $user, $session, $text);
                break;

            case 'AWAITING_TITLE':
                $this->handleAwaitingTitleStep($fromChatId, $user, $session, $text);
                break;

            case 'AWAITING_DESCRIPTION':
                $this->handleAwaitingDescriptionStep($fromChatId, $user, $session, $text);
                break;

            case 'AWAITING_STATUS':
                $this->handleAwaitingStatusStep($fromChatId, $user, $session, $text);
                break;

            case 'AWAITING_CONFIRMATION':
                $this->handleAwaitingConfirmationStep($fromChatId, $user, $session, $text);
                break;

            default:
                $session->resetToMenu();
                $this->sendMainMenu($fromChatId, $user);
                break;
        }
    }

    /**
     * Tampilkan Menu Utama
     */
    protected function sendMainMenu(string $fromChatId, User $user): void
    {
        $greeting = "Halo *{$user->name}* 👋\nAda yang bisa dibantu hari ini?\n\n"
            . "1️⃣ *Input Tugas Baru*\n"
            . "2️⃣ *Lihat Tugas Hari Ini*\n\n"
            . "Balas dengan mengetik angka pilihan (*1* atau *2*).";

        $this->waha->sendMessage($fromChatId, $greeting);
    }

    /**
     * Tahap 0: Pemilihan Menu Utama (1 atau 2)
     */
    protected function handleMenuStep(string $fromChatId, User $user, WhatsAppSession $session, string $input): void
    {
        if ($input === '1') {
            $session->update([
                'current_step' => 'AWAITING_TITLE',
                'temp_data' => [],
            ]);

            $this->waha->sendMessage(
                $fromChatId,
                "📝 *Input Tugas Baru*\n\n"
                . "Silakan ketik *Judul Tugas* yang ingin dicatat:\n"
                . "_(Ketik 'batal' kapan saja untuk membatalkan)_"
            );
        } elseif ($input === '2') {
            $this->showTodayTasks($fromChatId, $user);
        } else {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ Pilihan tidak dikenali. Silakan balas dengan angka *1* (Input Tugas) atau *2* (Lihat Tugas)."
            );
        }
    }

    /**
     * Tahap 1: Menerima Judul Tugas
     */
    protected function handleAwaitingTitleStep(string $fromChatId, User $user, WhatsAppSession $session, string $input): void
    {
        if (mb_strlen($input) < 3) {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ Judul tugas terlalu pendek (minimal 3 karakter). Silakan ketik kembali judul tugas:"
            );
            return;
        }

        $temp = $session->temp_data ?? [];
        $temp['title'] = $input;

        $session->update([
            'current_step' => 'AWAITING_DESCRIPTION',
            'temp_data' => $temp,
        ]);

        $this->waha->sendMessage(
            $fromChatId,
            "📌 Judul: *{$input}*\n\n"
            . "Ada rincian / deskripsi tambahan untuk tugas ini?\n"
            . "_(Ketik rincian deskripsi, atau ketik *skip* jika tidak ada)_"
        );
    }

    /**
     * Tahap 2: Menerima Deskripsi Tugas (bisa 'skip')
     */
    protected function handleAwaitingDescriptionStep(string $fromChatId, User $user, WhatsAppSession $session, string $input): void
    {
        $temp = $session->temp_data ?? [];

        if (strtolower($input) === 'skip' || $input === '-') {
            $temp['description'] = null;
        } else {
            $temp['description'] = $input;
        }

        $session->update([
            'current_step' => 'AWAITING_STATUS',
            'temp_data' => $temp,
        ]);

        $this->waha->sendMessage(
            $fromChatId,
            "📊 *Pilih Status Awal Tugas*:\n\n"
            . "1️⃣ Belum Dikerjakan\n"
            . "2️⃣ Sedang Dikerjakan\n"
            . "3️⃣ Selesai\n\n"
            . "Balas dengan angka *1*, *2*, atau *3*:"
        );
    }

    /**
     * Tahap 3: Menerima Status Tugas & Tampilkan Konfirmasi
     */
    protected function handleAwaitingStatusStep(string $fromChatId, User $user, WhatsAppSession $session, string $input): void
    {
        $statusMap = [
            '1' => ['key' => 'pending', 'label' => 'Belum Dikerjakan'],
            '2' => ['key' => 'in_progress', 'label' => 'Sedang Dikerjakan'],
            '3' => ['key' => 'done', 'label' => 'Selesai'],
        ];

        if (! isset($statusMap[$input])) {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ Pilihan status tidak valid. Silakan balas dengan angka:\n"
                . "1️⃣ Belum Dikerjakan\n"
                . "2️⃣ Sedang Dikerjakan\n"
                . "3️⃣ Selesai"
            );
            return;
        }

        $temp = $session->temp_data ?? [];
        $temp['status'] = $statusMap[$input]['key'];
        $temp['status_label'] = $statusMap[$input]['label'];

        $session->update([
            'current_step' => 'AWAITING_CONFIRMATION',
            'temp_data' => $temp,
        ]);

        $descDisplay = $temp['description'] ? $temp['description'] : '-';

        $this->waha->sendMessage(
            $fromChatId,
            "🔍 *Konfirmasi Data Tugas:*\n\n"
            . "📌 *Judul:* {$temp['title']}\n"
            . "📝 *Deskripsi:* {$descDisplay}\n"
            . "📊 *Status:* {$temp['status_label']}\n\n"
            . "Apakah data sudah benar?\n"
            . "Balas *Ya* untuk simpan, atau *Batal* untuk membatalkan."
        );
    }

    /**
     * Tahap 4: Konfirmasi Simpan Tugas
     */
    protected function handleAwaitingConfirmationStep(string $fromChatId, User $user, WhatsAppSession $session, string $input): void
    {
        $lower = strtolower($input);

        if (in_array($lower, ['ya', 'y', 'yes', 'simpan', 'ok', 'oke', 'benar', 'setuju'])) {
            $temp = $session->temp_data ?? [];
            $today = Carbon::today()->toDateString();

            $maxOrder = Task::where('user_id', $user->id)
                ->where('date', $today)
                ->max('sort_order') ?? 0;

            $status = $temp['status'] ?? 'pending';
            $completedAt = $status === 'done' ? Carbon::now() : null;

            Task::create([
                'user_id' => $user->id,
                'date' => $today,
                'title' => $temp['title'],
                'description' => $temp['description'] ?? null,
                'status' => $status,
                'source' => 'whatsapp',
                'sort_order' => $maxOrder + 1,
                'completed_at' => $completedAt,
            ]);

            $session->resetToMenu();

            $this->waha->sendMessage(
                $fromChatId,
                "✅ *Tugas Berhasil Dicatat!*\n\n"
                . "Tugas *\"{$temp['title']}\"* telah tersimpan ke sistem TURIMA FRAM dan tersinkron ke dashboard web.\n\n"
                . "Terima kasih, *{$user->name}*! 🙏\n"
                . "Ketik *menu* jika ingin melakukan hal lain."
            );
        } elseif (in_array($lower, ['tidak', 'batal', 'no', 't'])) {
            $session->resetToMenu();

            $this->waha->sendMessage(
                $fromChatId,
                "❌ *Pencatatan Tugas Dibatalkan.*\n\n"
                . "Ketik *menu* jika ingin kembali ke menu utama."
            );
        } else {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ Balasan tidak dikenali. Silakan balas *Ya* untuk menyimpan atau *Batal* untuk membatalkan."
            );
        }
    }

    /**
     * Tampilkan Daftar Tugas Karyawan Hari Ini (Opsi Menu 2)
     */
    protected function showTodayTasks(string $fromChatId, User $user): void
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $dateFormatted = $today->format('d-m-Y');

        $tasks = Task::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->orderBy('sort_order')
            ->get();

        if ($tasks->isEmpty()) {
            $this->waha->sendMessage(
                $fromChatId,
                "📋 *Daftar Tugas Hari Ini ({$dateFormatted})*\n\n"
                . "Belum ada tugas yang tercatat untuk hari ini.\n\n"
                . "Ketik *1* untuk mencatat tugas baru."
            );
            return;
        }

        $message = "📋 *Daftar Tugas Anda Hari Ini ({$dateFormatted})*:\n\n";

        foreach ($tasks as $index => $task) {
            $num = $index + 1;
            $statusBadge = match ($task->status) {
                'done' => '✅ [Selesai]',
                'in_progress' => '⏳ [Sedang Dikerjakan]',
                default => '📌 [Belum Selesai]',
            };

            $sourceBadge = $task->source === 'whatsapp' ? ' 📱' : '';

            $message .= "{$num}. {$statusBadge} *{$task->title}*{$sourceBadge}\n";

            if ($task->description) {
                $message .= "   └ 📝 _{$task->description}_\n";
            }

            if ($task->status === 'done' && $task->completed_at) {
                $time = Carbon::parse($task->completed_at)->format('H:i');
                $message .= "   └ ⏱️ Selesai pukul {$time}\n";
            }

            $message .= "\n";
        }

        $message .= "Ketik *1* untuk menambah tugas baru, atau ketik *menu* untuk kembali.";

        $this->waha->sendMessage($fromChatId, $message);
    }

    /**
     * Cari user karyawan berdasarkan nomor telepon yang cocok.
     */
    protected function findUserByPhone(string $rawPhone): ?User
    {
        $normalized = User::normalizePhoneNumber($rawPhone);

        if (! $normalized) {
            return null;
        }

        // Cari exact match atau kemiripan awalan 08/62
        $user = User::where('phone', $normalized)->first();

        if ($user) {
            return $user;
        }

        // Coba alternatif jika disimpan dengan awalan '08'
        if (str_starts_with($normalized, '628')) {
            $local = '08' . substr($normalized, 3);
            $user = User::where('phone', $local)->first();
            if ($user) return $user;
        }

        return null;
    }
}
