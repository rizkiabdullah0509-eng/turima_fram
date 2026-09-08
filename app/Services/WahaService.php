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
            $client = Http::timeout(10)->asJson();

            if ($this->apiKey) {
                $client->withHeaders(['X-Api-Key' => $this->apiKey]);
            }

            $response = $client->post("{$this->baseUrl}/api/sendText", [
                'session' => $this->session,
                'chatId' => $formattedChatId,
                'text' => $text,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WAHA sendText failed', [
                'status' => $response->status(),
                'response' => $response->body(),
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
            $client = Http::timeout(5);
            if ($this->apiKey) {
                $client->withHeaders(['X-Api-Key' => $this->apiKey]);
            }

            $response = $client->get("{$this->baseUrl}/api/sessions/{$this->session}");

            return $response->successful() ? $response->json() : null;
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
