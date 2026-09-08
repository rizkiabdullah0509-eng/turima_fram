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
     * Restart sesi WAHA (membuat QR code baru jika status FAILED atau STOPPED).
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

            if ($httpCode === 404) {
                return $this->createAndStartSession();
            }

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable $e) {
            Log::error('WAHA restartSession exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Buat dan jalankan sesi baru di WAHA jika belum ada.
     */
    public function createAndStartSession(): bool
    {
        try {
            $payload = json_encode(['name' => $this->session]);
            $ch = curl_init("{$this->baseUrl}/api/sessions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $headers = ['Content-Type: application/json'];
            if ($this->apiKey) {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_exec($ch);
            curl_close($ch);

            $chStart = curl_init("{$this->baseUrl}/api/sessions/{$this->session}/start");
            curl_setopt($chStart, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chStart, CURLOPT_POST, true);
            curl_setopt($chStart, CURLOPT_TIMEOUT, 10);
            $headersStart = [];
            if ($this->apiKey) {
                $headersStart[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($chStart, CURLOPT_HTTPHEADER, $headersStart);
            curl_exec($chStart);
            $code = curl_getinfo($chStart, CURLINFO_HTTP_CODE);
            curl_close($chStart);

            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ambil raw image data PNG QR Code dari WAHA.
     * Jika status sesi FAILED / STOPPED, otomatis restart terlebih dahulu.
     */
    public function getQrCodeImage(): ?string
    {
        try {
            $status = $this->getSessionStatus();
            $currStatus = $status['status'] ?? null;

            if ($currStatus === 'FAILED' || $currStatus === 'STOPPED' || empty($currStatus)) {
                $this->restartSession();
                usleep(800000);
            }

            $ch = curl_init("{$this->baseUrl}/api/{$this->session}/auth/qr");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

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
            return null;
        }
    }

    /**
     * Format nomor WhatsApp menjadi chatId WAHA (misal: 6281234567890@c.us).
     */
    public function formatChatId(string $phoneOrChatId): string
    {
        if (str_ends_with($phoneOrChatId, '@c.us') || str_ends_with($phoneOrChatId, '@g.us')) {
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
