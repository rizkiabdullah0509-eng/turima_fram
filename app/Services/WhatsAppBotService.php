<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public function handleIncomingMessage(string $fromChatId, string $messageText, array $payload = []): void
    {
        $phone = $this->resolvePhoneNumber($fromChatId, $payload);
        $text = trim($messageText);

        if ($text === '') {
            return;
        }

        // 1. Verifikasi apakah nomor pengirim terdaftar di sistem
        $user = $this->findUserByPhone($phone, $fromChatId);

        if (! $user) {
            $cleanId = WahaService::extractPhoneNumber($fromChatId);
            $displayId = ($phone !== $cleanId && !empty($phone)) ? "{$phone}" : $cleanId;

            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ *Nomor Tidak Terdaftar*\n\n"
                . "Nomor WhatsApp Anda ({$displayId}) belum terdaftar di sistem *TURIMA FRAM*.\n\n"
                . "Silakan masukkan nomor WhatsApp Anda pada pengaturan akun web atau hubungi Administrator."
            );
            return;
        }

        // 2. Verifikasi hak akses: Khusus Manajer, Admin, atau Karyawan dengan hak akses jadwal/tugas
        if (! $user->canManageTeamSchedule()) {
            $this->waha->sendMessage(
                $fromChatId,
                "⚠️ *Akses Ditolak*\n\n"
                . "Halo *{$user->name}*!\n"
                . "Fitur WhatsApp Bot ini dikhususkan bagi *Manajer* dan *Karyawan yang diberi akses* untuk mengelola dan mengunggah tugas harian tim.\n\n"
                . "Silakan hubungi Manajer jika Anda membutuhkan izin akses kelola tugas."
            );
            return;
        }

        // Simpan / update sesi percakapan
        $session = WhatsAppSession::firstOrCreate(
            ['phone_number' => $phone],
            [
                'user_id' => $user->id,
                'current_step' => 'IDLE',
                'last_active_at' => Carbon::now(),
            ]
        );
        $session->last_active_at = Carbon::now();
        $session->save();

        $cleanText = strtolower(trim($text));

        // 3. Perintah Bantuan / Menu
        if (in_array($cleanText, ['menu', 'halo', 'hai', 'hi', 'help', 'bantuan', 'start', 'p'])) {
            $this->sendHelpMenu($fromChatId, $user);
            return;
        }

        // 4. Perintah Daftar Karyawan
        if (in_array($cleanText, ['karyawan', 'list', 'daftar', 'pegawai'])) {
            $this->sendEmployeeList($fromChatId);
            return;
        }

        // 5. Perintah Pantau Progres Tugas Hari Ini
        if (in_array($cleanText, ['progres', 'progress', 'status', 'pantau', 'cek'])) {
            $this->sendTeamProgress($fromChatId);
            return;
        }

        // 6. Coba parsing format penugasan tugas harian
        $parsed = $this->parseTasksText($text);

        if (! empty($parsed['assignments'])) {
            $this->executeTaskAssignment($fromChatId, $user, $parsed);
            return;
        }

        // 7. Jika pesan tidak dikenali
        $this->waha->sendMessage(
            $fromChatId,
            "Halo! Ada yang bisa saya bantu? Perkenalkan saya *Turima Bot* 🤖\n\n"
            . "📋 *Tugas apa yang perlu saya upload?*\n\n"
            . "Silakan kirimkan nama karyawan dan daftar tugasnya, contoh:\n\n"
            . "*budi*\n"
            . "1. membersikan rumput\n"
            . "2. ngasah arit\n"
            . "3. mencuci mobil\n\n"
            . "*cici*\n"
            . "1. membersihkan selokan\n"
            . "2. ngasih makan ayam\n"
            . "3. membuat nasi\n\n"
            . "Ketik *progres* untuk melihat penyelesaian tugas hari ini atau *karyawan* untuk melihat daftar karyawan."
        );
    }

    /**
     * Tampilkan menu panduan penugasan tugas
     */
    public function sendHelpMenu(string $fromChatId, User $user): void
    {
        $message = "Halo! Ada yang bisa saya bantu? Perkenalkan saya *Turima Bot* 🤖\n\n"
            . "📋 *Tugas apa yang perlu saya upload?*\n\n"
            . "Silakan kirimkan nama karyawan dan daftar tugasnya, contoh:\n\n"
            . "budi\n"
            . "1. membersikan rumput\n"
            . "2. ngasah arit\n"
            . "3. mencuci mobil\n\n"
            . "cici\n"
            . "1. membersihkan selokan\n"
            . "2. ngasih makan ayam\n"
            . "3. membuat nasi\n\n"
            . "💡 *Perintah Lainnya:*\n"
            . "• Gunakan *semua* sebagai nama untuk menugaskan ke seluruh karyawan tim.\n"
            . "• Tambahkan kata *besok* di baris paling atas jika ingin menjadwalkan untuk besok.\n"
            . "• Ketik *progres* untuk melihat penyelesaian tugas tim hari ini.\n"
            . "• Ketik *karyawan* untuk melihat daftar username karyawan aktif.";

        $this->waha->sendMessage($fromChatId, $message);
    }

    /**
     * Tampilkan daftar karyawan aktif
     */
    public function sendEmployeeList(string $fromChatId): void
    {
        $employees = User::where('role', 'employee')->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $this->waha->sendMessage($fromChatId, "Belum ada karyawan terdaftar di sistem.");
            return;
        }

        $lines = ["👥 *Daftar Karyawan Aktif:*"];
        foreach ($employees as $i => $e) {
            $lines[] = ($i + 1) . ". *{$e->name}* (username: `{$e->username}`)";
        }
        $lines[] = "\nKetik nama/username karyawan di atas diikuti daftar tugasnya untuk memberikan tugas.";

        $this->waha->sendMessage($fromChatId, implode("\n", $lines));
    }

    /**
     * Tampilkan ringkasan progres pengerjaan tugas tim hari ini
     */
    public function sendTeamProgress(string $fromChatId, ?string $targetDate = null): void
    {
        $date = $targetDate ?: Carbon::today()->toDateString();
        $dateLabel = Carbon::parse($date)->locale('id')->isoFormat('dddd, DD-MM-YYYY');

        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $tasks = Task::where('date', $date)->get();

        if ($tasks->isEmpty()) {
            $this->waha->sendMessage(
                $fromChatId,
                "📊 *Progres Tugas Tim*\n📅 {$dateLabel}\n\nBelum ada tugas yang ditugaskan untuk tanggal ini.\n\nKetik *menu* untuk melihat contoh cara menugaskan."
            );
            return;
        }

        $totalDone = $tasks->where('status', 'done')->count();
        $totalAll = $tasks->count();
        $percent = $totalAll > 0 ? round(($totalDone / $totalAll) * 100) : 0;

        $lines = [
            "📊 *Progres Tugas Tim*",
            "📅 {$dateLabel}\n"
        ];

        foreach ($employees as $emp) {
            $empTasks = $tasks->where('user_id', $emp->id);
            if ($empTasks->isEmpty()) {
                continue;
            }

            $done = $empTasks->where('status', 'done')->count();
            $inProgress = $empTasks->where('status', 'in_progress')->count();
            $pending = $empTasks->where('status', 'pending')->count();
            $total = $empTasks->count();

            $statusText = "✓ {$done} Selesai";
            if ($inProgress > 0) {
                $statusText .= ", ⏳ {$inProgress} Sedang";
            }
            if ($pending > 0) {
                $statusText .= ", {$pending} Belum";
            }

            $badge = ($done === $total) ? " 🎉" : "";
            $lines[] = "👤 *{$emp->name}* ({$total} tugas)\n   └ {$statusText}{$badge}";
        }

        $lines[] = "\n──────────────────";
        $lines[] = "📈 *Total Tim:* {$totalDone}/{$totalAll} tugas selesai ({$percent}%)";

        $this->waha->sendMessage($fromChatId, implode("\n", $lines));
    }

    /**
     * Parser teks penugasan multi-karyawan.
     * Mendukung:
     * budi
     * 1. membersikan rumput
     * 2. ngasah arit
     *
     * cici
     * 1. membersihkan selokan
     */
    public function parseTasksText(string $text): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', trim($text));
        $employees = User::where('role', 'employee')->get();

        // Index karyawan untuk pencarian fleksibel
        $employeeLookup = [];
        foreach ($employees as $emp) {
            $employeeLookup[strtolower(trim($emp->username))] = $emp;
            $employeeLookup[strtolower(trim($emp->name))] = $emp;
            // Ambil nama depan
            $firstName = strtolower(explode(' ', trim($emp->name))[0]);
            if (! isset($employeeLookup[$firstName])) {
                $employeeLookup[$firstName] = $emp;
            }
        }

        $targetDate = Carbon::today()->toDateString();
        $assignments = [];
        $unrecognized = [];

        $currentKey = null; // 'all' atau ID user
        $currentEmployee = null;
        $currentEmployeeLabel = '';
        $currentTasks = [];

        $flushCurrent = function () use (&$assignments, &$currentKey, &$currentEmployee, &$currentEmployeeLabel, &$currentTasks) {
            if ($currentKey !== null && ! empty($currentTasks)) {
                $assignments[] = [
                    'key' => $currentKey,
                    'is_all' => ($currentKey === 'all'),
                    'employee' => $currentEmployee,
                    'label' => $currentEmployeeLabel,
                    'tasks' => $currentTasks,
                ];
            }
            $currentTasks = [];
        };

        foreach ($rawLines as $lineIndex => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Cek baris pertama apakah mengatur tanggal
            if ($lineIndex === 0) {
                $lowerFirst = strtolower($trimmed);
                if (in_array($lowerFirst, ['besok', 'tomorrow'])) {
                    $targetDate = Carbon::tomorrow()->toDateString();
                    continue;
                }
                if (in_array($lowerFirst, ['hari ini', 'today'])) {
                    $targetDate = Carbon::today()->toDateString();
                    continue;
                }
                if (preg_match('/^tanggal[:\s]+(\d{4}-\d{2}-\d{2})$/i', $trimmed, $m)) {
                    $targetDate = $m[1];
                    continue;
                }
                if (preg_match('/^tanggal[:\s]+(\d{2}-\d{2}-\d{4})$/i', $trimmed, $m)) {
                    $targetDate = Carbon::createFromFormat('d-m-Y', $m[1])->toDateString();
                    continue;
                }
            }

            // Bersihkan format markdown asterisks (*), titik dua (:), pagar (#)
            $cleanHeaderCandidate = strtolower(trim(preg_replace('/^[\*\_\|\#\:\-\s]+|[\*\_\|\#\:\-\s]+$/u', '', $trimmed)));

            // Cek apakah baris ini adalah nama karyawan atau 'semua'
            if ($cleanHeaderCandidate === 'semua' || $cleanHeaderCandidate === 'all' || $cleanHeaderCandidate === 'semua karyawan') {
                $flushCurrent();
                $currentKey = 'all';
                $currentEmployee = null;
                $currentEmployeeLabel = 'Semua Karyawan';
                continue;
            }

            if (isset($employeeLookup[$cleanHeaderCandidate])) {
                $flushCurrent();
                $emp = $employeeLookup[$cleanHeaderCandidate];
                $currentKey = $emp->id;
                $currentEmployee = $emp;
                $currentEmployeeLabel = $emp->name;
                continue;
            }

            // Jika baris ini diawali angka/bullet atau baris tugas
            // Contoh: "1. membersihkan rumput", "2) ngasah arit", "- cek kandang"
            if ($currentKey !== null) {
                $cleanTask = preg_replace('/^(\d+[\.\)\-]\s*|[\-\*\•\>]\s*)/u', '', $trimmed);
                $cleanTask = trim($cleanTask);

                if (mb_strlen($cleanTask) >= 2) {
                    $currentTasks[] = $cleanTask;
                }
            } else {
                // Jika belum ada header karyawan, tapi baris tampak seperti nama yang tidak terdaftar
                if (! preg_match('/^\d+[\.\)]/', $trimmed) && mb_strlen($cleanHeaderCandidate) >= 2) {
                    $unrecognized[] = $cleanHeaderCandidate;
                }
            }
        }

        $flushCurrent();

        return [
            'date' => $targetDate,
            'assignments' => $assignments,
            'unrecognized' => array_unique($unrecognized),
        ];
    }

    /**
     * Eksekusi penyimpanan tugas ke database
     */
    protected function executeTaskAssignment(string $fromChatId, User $author, array $parsed): void
    {
        $date = $parsed['date'];
        $assignments = $parsed['assignments'];
        $unrecognized = $parsed['unrecognized'] ?? [];

        $dateFormatted = Carbon::parse($date)->locale('id')->isoFormat('dddd, DD-MM-YYYY');
        $allEmployees = User::where('role', 'employee')->get();

        $savedReport = [];
        $totalCreated = 0;

        DB::transaction(function () use ($assignments, $date, $allEmployees, &$savedReport, &$totalCreated) {
            foreach ($assignments as $group) {
                $targetEmployees = $group['is_all'] ? $allEmployees : collect([$group['employee']]);

                foreach ($targetEmployees as $emp) {
                    if (! $emp) continue;

                    $maxOrder = Task::where('user_id', $emp->id)
                        ->where('date', $date)
                        ->max('sort_order') ?? 0;

                    foreach ($group['tasks'] as $i => $taskTitle) {
                        Task::create([
                            'user_id' => $emp->id,
                            'date' => $date,
                            'title' => $taskTitle,
                            'status' => 'pending',
                            'source' => 'whatsapp',
                            'sort_order' => $maxOrder + $i + 1,
                        ]);
                        $totalCreated++;
                    }

                    $savedReport[] = [
                        'name' => $emp->name,
                        'tasks' => $group['tasks'],
                    ];
                }
            }
        });

        // Buat record notifikasi web
        Notice::create([
            'message' => "{$totalCreated} tugas harian ditugaskan via WhatsApp Bot untuk tanggal {$date}.",
        ]);

        // Susun balasan WhatsApp
        $reply = [
            "✅ *Tugas Berhasil Ditugaskan!*",
            "📅 Tanggal: *{$dateFormatted}*\n",
        ];

        foreach ($savedReport as $item) {
            $count = count($item['tasks']);
            $reply[] = "👤 *{$item['name']}* ({$count} tugas):";
            foreach ($item['tasks'] as $idx => $t) {
                $reply[] = "  " . ($idx + 1) . ". {$t}";
            }
            $reply[] = "";
        }

        if (! empty($unrecognized)) {
            $reply[] = "⚠️ _Catatan: Nama *" . implode(', ', $unrecognized) . "* tidak ditemukan di daftar karyawan._\n";
        }

        $reply[] = "Total *{$totalCreated} tugas* telah langsung masuk ke aplikasi karyawan.";
        $reply[] = "_Ketik *progres* untuk memantau status pengerjaan._";

        $this->waha->sendMessage($fromChatId, implode("\n", $reply));
    }

    /**
     * Memetakan identifier pengirim (bisa nomor standar atau WhatsApp LID)
     * ke nomor telepon riil yang terdaftar.
     */
    public function resolvePhoneNumber(string $fromChatId, array $payload = []): string
    {
        // 1. Cek remoteJidAlt di dalam payload pesan webhook
        $altCandidate = $payload['_data']['key']['remoteJidAlt']
            ?? $payload['key']['remoteJidAlt']
            ?? $payload['remoteJidAlt']
            ?? $payload['_data']['participant']
            ?? $payload['participant']
            ?? null;

        if (!empty($altCandidate) && !str_contains($altCandidate, '@lid')) {
            $extracted = WahaService::extractPhoneNumber($altCandidate);
            if (strlen($extracted) >= 8 && strlen($extracted) <= 16) {
                if (str_contains($fromChatId, '@lid')) {
                    $cleanLid = WahaService::extractPhoneNumber($fromChatId);
                    Cache::forever("waha_lid_{$cleanLid}", $extracted);
                }
                return $extracted;
            }
        }

        $cleanId = WahaService::extractPhoneNumber($fromChatId);

        // 2. Jika bukan LID (nomor HP standar panjang <= 14 digit dan tanpa @lid)
        if (!str_contains($fromChatId, '@lid') && strlen($cleanId) <= 14) {
            return $cleanId;
        }

        // 3. Cek pemetaan LID yang sudah tersimpan di Cache
        $cached = Cache::get("waha_lid_{$cleanId}");
        if ($cached) {
            return $cached;
        }

        // 4. Cek sesi WAHA jika pengirim adalah bot itu sendiri
        try {
            $session = $this->waha->getSessionStatus();
            $meLid = $session['me']['lid'] ?? '';
            $meId = $session['me']['id'] ?? '';
            if (!empty($meLid) && str_contains($meLid, $cleanId) && !empty($meId)) {
                $phone = WahaService::extractPhoneNumber($meId);
                Cache::forever("waha_lid_{$cleanId}", $phone);
                return $phone;
            }
        } catch (\Throwable $e) {
            Log::warning('WAHA resolve LID getSessionStatus error: ' . $e->getMessage());
        }

        // 5. Cek dari daftar percakapan aktif WAHA (/api/{session}/chats)
        try {
            $chats = $this->waha->getChats();
            if (is_array($chats)) {
                foreach ($chats as $chat) {
                    $chatLid = $chat['accountLid'] ?? '';
                    $pnJid = $chat['pnJid'] ?? '';
                    if (!empty($chatLid) && str_contains($chatLid, $cleanId) && !empty($pnJid)) {
                        $phone = WahaService::extractPhoneNumber($pnJid);
                        Cache::forever("waha_lid_{$cleanId}", $phone);
                        return $phone;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WAHA resolve LID getChats error: ' . $e->getMessage());
        }

        return $cleanId;
    }

    /**
     * Cari user berdasarkan nomor WhatsApp atau raw identifier (LID).
     */
    protected function findUserByPhone(string $phone, ?string $rawIdentifier = null): ?User
    {
        $normalized = User::normalizePhoneNumber($phone);

        // 1. Cari langsung berdasarkan nomor yang sudah dinormalisasi
        $user = User::where('phone', $normalized)->first();
        if ($user) {
            return $user;
        }

        // 2. Cari dengan raw phone
        $user = User::where('phone', $phone)->first();
        if ($user) {
            return $user;
        }

        // 3. Jika ada rawIdentifier (misal nomor LID atau chatId), cek jika tersimpan di kolom phone
        if ($rawIdentifier) {
            $cleanRaw = WahaService::extractPhoneNumber($rawIdentifier);
            if (!empty($cleanRaw)) {
                $user = User::where('phone', $cleanRaw)->first();
                if ($user) {
                    return $user;
                }
            }
        }

        // 4. Fallback pencarian fleksibel
        return User::all()->first(function (User $u) use ($phone, $normalized, $rawIdentifier) {
            if (! $u->phone) {
                return false;
            }
            $uNorm = User::normalizePhoneNumber($u->phone);
            if ($uNorm === $normalized || $u->phone === $phone) {
                return true;
            }
            if ($rawIdentifier) {
                $cleanRaw = WahaService::extractPhoneNumber($rawIdentifier);
                if ($u->phone === $cleanRaw) {
                    return true;
                }
            }
            return false;
        });
    }
}
