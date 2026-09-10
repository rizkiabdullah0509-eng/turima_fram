<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Aplikasi ini adalah SPA (Single Page Application) berbasis Vue 3.
| Semua route non-API diarahkan ke satu view yang memuat aplikasi Vue,
| lalu routing halaman ditangani oleh Vue Router di sisi frontend.
*/

/*
| Fallback Handler untuk File Storage (Foto Absensi & Tugas)
| Memastikan foto absensi/tugas tetap dapat diakses meskipun symlink public/storage
| belum dibuat, rusak, atau dibatasi oleh server/cPanel hosting.
*/
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (! file_exists($filePath) || is_dir($filePath)) {
        abort(404, 'File foto tidak ditemukan.');
    }

    $mimeType = @mime_content_type($filePath) ?: 'image/jpeg';

    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
