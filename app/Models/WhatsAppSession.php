<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone_number',
        'user_id',
        'current_step',
        'temp_data',
        'last_active_at',
    ];

    protected $casts = [
        'temp_data' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cek apakah sesi sudah kedaluwarsa (misal > 15 menit).
     */
    public function isExpired(int $timeoutMinutes = 15): bool
    {
        if (! $this->last_active_at) {
            return false;
        }

        return $this->last_active_at->diffInMinutes(Carbon::now()) > $timeoutMinutes;
    }

    /**
     * Reset sesi kembali ke menu awal.
     */
    public function resetToMenu(): void
    {
        $this->update([
            'current_step' => 'MENU',
            'temp_data' => null,
            'last_active_at' => Carbon::now(),
        ]);
    }
}
