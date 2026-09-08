<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WahaService;
use App\Services\WhatsAppBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected WhatsAppBotService $botService;
    protected WahaService $wahaService;

    public function __construct(WhatsAppBotService $botService, WahaService $wahaService)
    {
        $this->botService = $botService;
        $this->wahaService = $wahaService;
    }

    /**
     * Webhook handler untuk event dari WAHA.
     */
    public function handle(Request $request)
    {
        $event = $request->input('event');
        $payload = $request->input('payload');

        if ($event !== 'message' || empty($payload)) {
            return response()->json(['status' => 'ignored', 'reason' => 'Not a message event'], 200);
        }

        // Abaikan pesan jika dikirim oleh bot sendiri (fromMe == true)
        if (!empty($payload['fromMe'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'fromMe message'], 200);
        }

        // 1. Abaikan pesan lampau/riwayat saat sinkronisasi sesi baru (lebih dari 45 detik yang lalu)
        $timestamp = $payload['timestamp'] ?? null;
        if (!empty($timestamp) && (time() - (int)$timestamp) > 45) {
            return response()->json(['status' => 'ignored', 'reason' => 'Historical sync message'], 200);
        }

        // 2. Cegah pemrosesan ganda pesan yang sama (Deduplication)
        $messageId = $payload['id'] ?? null;
        if (!empty($messageId)) {
            if (Cache::has("waha_msg_{$messageId}")) {
                return response()->json(['status' => 'ignored', 'reason' => 'Duplicate message'], 200);
            }
            Cache::put("waha_msg_{$messageId}", true, 180);
        }

        $from = $payload['from'] ?? null;
        $body = $payload['body'] ?? '';

        if (empty($from)) {
            return response()->json(['status' => 'ignored', 'reason' => 'Missing from'], 200);
        }

        // Abaikan pesan grup (@g.us)
        if (str_contains($from, '@g.us')) {
            return response()->json(['status' => 'ignored', 'reason' => 'Group message'], 200);
        }

        // Abaikan broadcast status (@broadcast)
        if (str_contains($from, '@broadcast')) {
            return response()->json(['status' => 'ignored', 'reason' => 'Broadcast message'], 200);
        }

        try {
            $this->botService->handleIncomingMessage($from, (string) $body, $payload);
            return response()->json(['status' => 'success'], 200);
        } catch (\Throwable $e) {
            Log::error('Error processing WhatsApp message: ' . $e->getMessage(), [
                'from' => $from,
                'body' => $body,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cek status koneksi sesi WAHA.
     */
    public function status()
    {
        $sessionStatus = $this->wahaService->getSessionStatus();
        return response()->json([
            'waha' => $sessionStatus,
            'configured_url' => config('waha.url'),
            'session_name' => config('waha.session'),
        ]);
    }

    /**
     * Restart sesi WAHA (membuat QR baru jika sesi FAILED atau STOPPED).
     */
    public function restart()
    {
        $success = $this->wahaService->restartSession();
        $newStatus = $this->wahaService->getSessionStatus();

        return response()->json([
            'success' => $success,
            'waha' => $newStatus,
        ]);
    }

    /**
     * Mengambil gambar QR code live dari WAHA.
     */
    public function qr()
    {
        $imageData = $this->wahaService->getQrCodeImage();

        if (! $imageData) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'QR Code tidak tersedia atau sesi WhatsApp sudah terhubung.',
            ], 404);
        }

        return response($imageData, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
