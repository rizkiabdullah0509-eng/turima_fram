<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'color'];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /** Durasi shift dalam jam (menghitung shift lewat tengah malam) */
    public function durationHours(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }
        return round($start->diffInMinutes($end) / 60, 1);
    }
}
