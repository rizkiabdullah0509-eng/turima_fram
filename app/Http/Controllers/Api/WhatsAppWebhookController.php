<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WahaService;
use App\Services\WhatsAppBotService;
use Illuminate\Http\Request;
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

        Log::info('WAHA Webhook received', [
            'event' => $event,
            'session' => $request->input('session'),
        ]);

        if ($event !== 'message' || empty($payload)) {
            return response()->json(['status' => 'ignored', 'reason' => 'Not a message event'], 200);
        }

        // Abaikan pesan jika dikirim oleh bot sendiri (fromMe == true)
        if (!empty($payload['fromMe'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'fromMe message'], 200);
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
        usleep(600000);
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
