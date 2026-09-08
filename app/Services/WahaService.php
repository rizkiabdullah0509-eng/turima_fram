<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $session;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('waha.url', 'http://127.0.0.1:3000'), '/');
        $this->apiKey = config('waha.api_key');
        $this->session = config('waha.session', 'default');
    }

    /**
     * Kirim pesan teks WhatsApp ke nomor tujuan via WAHA API.
     *
     * @param string $chatId Format chatId WAHA: "6281234567890@c.us" atau cukup nomor HP
     * @param string $text Konten pesan balasan
     */
    public function sendMessage(string $chatId, string $text): bool
    {
        $formattedChatId = $this->formatChatId($chatId);

        try {
            $payload = json_encode([
                'session' => $this->session,
                'chatId' => $formattedChatId,
                'text' => $text,
            ]);

            $ch = curl_init("{$this->baseUrl}/api/sendText");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $headers = ['Content-Type: application/json'];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            Log::error('WAHA sendText failed', [
                'status' => $httpCode,
                'response' => $response,
                'curl_error' => $err,
                'chatId' => $formattedChatId,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('WAHA sendText exception: ' . $e->getMessage(), [
                'chatId' => $formattedChatId,
            ]);

            return false;
        }
    }

    /**
     * Cek status sesi WhatsApp di WAHA.
     */
    public function getSessionStatus(): ?array
    {
        try {
            $ch = curl_init("{$this->baseUrl}/api/sessions/{$this->session}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && $response) {
                return json_decode($response, true);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Restart sesi WAHA. Jika gagal atau status FAILED, lakukan reset penuh.
     */
    public function restartSession(): bool
    {
        try {
            $ch = curl_init("{$this->baseUrl}/api/sessions/{$this->session}/restart");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            // Jika restart gagal atau sesi 404, lakukan reset total
            return $this->resetSessionCompletely();
        } catch (\Throwable $e) {
            Log::error('WAHA restartSession exception: ' . $e->getMessage());
            return $this->resetSessionCompletely();
        }
    }

    /**
     * Hapus total sesi yang rusak/korup dan buat sesi baru dari nol.
     */
    public function resetSessionCompletely(): bool
    {
        try {
            $chDel = curl_init("{$this->baseUrl}/api/sessions/{$this->session}");
            curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chDel, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($chDel, CURLOPT_TIMEOUT, 4);

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($chDel, CURLOPT_HTTPHEADER, $headers);
            curl_exec($chDel);
            curl_close($chDel);

            return $this->createAndStartSession();
        } catch (\Throwable $e) {
            Log::error('WAHA resetSessionCompletely exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Buat dan jalankan sesi baru di WAHA jika belum ada.
     */
    public function createAndStartSession(): bool
    {
        try {
            $payload = json_encode([
                'name' => $this->session,
                'start' => true,
                'config' => [
                    'noweb' => [
                        'store' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ]);

            $ch = curl_init("{$this->baseUrl}/api/sessions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $headers = ['Content-Type: application/json'];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            Log::error('createAndStartSession exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil raw image data PNG QR Code dari WAHA secara instan tanpa blocking loop.
     */
    public function getQrCodeImage(): ?string
    {
        try {
            $status = $this->getSessionStatus();
            $currStatus = $status['status'] ?? null;

            if ($currStatus === 'WORKING') {
                return null;
            }

            // Jika status tidak sehat, pulihkan sesi
            if ($currStatus === 'FAILED' || $currStatus === 'STOPPED' || empty($currStatus)) {
                $this->resetSessionCompletely();
            }

            // Ambil QR Code PNG dari endpoint WAHA dengan timeout cepat
            $ch = curl_init("{$this->baseUrl}/api/{$this->session}/auth/qr");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
                return $response;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('getQrCodeImage exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ambil daftar seluruh percakapan aktif dari WAHA untuk resolusi pemetaan LID ke nomor telepon.
     */
    public function getChats(): array
    {
        try {
            $ch = curl_init("{$this->baseUrl}/api/{$this->session}/chats");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300 && !empty($res)) {
                $data = json_decode($res, true);
                return is_array($data) ? $data : [];
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('WAHA getChats exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format nomor WhatsApp menjadi chatId WAHA (misal: 6281234567890@c.us).
     */
    public function formatChatId(string $phoneOrChatId): string
    {
        if (
            str_ends_with($phoneOrChatId, '@c.us') || 
            str_ends_with($phoneOrChatId, '@g.us') || 
            str_ends_with($phoneOrChatId, '@lid') || 
            str_ends_with($phoneOrChatId, '@s.whatsapp.net')
        ) {
            return $phoneOrChatId;
        }

        $clean = preg_replace('/[^\d]/', '', $phoneOrChatId);

        if (str_starts_with($clean, '08')) {
            $clean = '628' . substr($clean, 2);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '628' . substr($clean, 1);
        } elseif (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }

        return $clean . '@c.us';
    }

    /**
     * Ekstrak nomor murni (tanpa @c.us) dari chatId atau payload WAHA.
     */
    public static function extractPhoneNumber(string $chatId): string
    {
        $phone = explode('@', $chatId)[0];
        return preg_replace('/[^\d]/', '', $phone);
    }
}
