<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        //
    }

    public function render($request, Throwable $e)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $status = 500;
            if (method_exists($e, 'getStatusCode')) {
                $status = $e->getStatusCode();
            } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                $status = 422;
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $status = 401;
            } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status = 404;
            }

            $message = $e->getMessage();
            if (! $message) {
                $message = match ($status) {
                    401 => 'Sesi Anda telah berakhir. Silakan masuk kembali.',
                    403 => 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
                    404 => 'Data yang diminta tidak ditemukan.',
                    422 => 'Data yang dikirim belum valid.',
                    default => 'Terjadi kesalahan pada server.',
                };
            }

            return response()->json(['message' => $message], $status);
        }

        return parent::render($request, $e);
    }
}
