<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Membatasi akses route berdasarkan role, ATAU jika user memiliki
     * flag can_manage_schedule (untuk karyawan yang diberi akses khusus).
     *
     * Pemakaian:
     *   ->middleware('role:manager,admin')         — hanya role tersebut
     *   ->middleware('role:manager,admin,schedule') — role tersebut ATAU karyawan dgn can_manage_schedule
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Anda harus login terlebih dahulu.'], 401);
        }

        // Cek apakah role user ada di daftar role yang diizinkan
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Cek apakah route mengizinkan karyawan dengan akses jadwal ("schedule")
        if (in_array('schedule', $roles, true) && $user->isEmployee() && $user->can_manage_schedule) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Anda tidak memiliki akses ke fitur ini.',
        ], 403);
    }
}
