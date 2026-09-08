<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WAHA (WhatsApp HTTP API) Configuration
    |--------------------------------------------------------------------------
    | Server WAHA berjalan via Docker (port 3000).
    | Dokumentasi: https://waha.devlike.pro/
    */
    'url' => env('WAHA_URL', 'http://127.0.0.1:3000'),

    'api_key' => env('WAHA_API_KEY', null),

    'session' => env('WAHA_SESSION', 'default'),

    // Secret token untuk validasi webhook masuk (opsional)
    'webhook_secret' => env('WAHA_WEBHOOK_SECRET', null),

    // Batas timeout sesi percakapan bot (menit)
    'session_timeout_minutes' => (int) env('WAHA_SESSION_TIMEOUT', 15),
];
