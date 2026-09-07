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

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
