<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'date', 'clock_in', 'clock_out', 'clock_in_photo', 'clock_out_photo',
        'clock_in_lat', 'clock_in_lng', 'clock_in_location_name',
        'clock_in_desa', 'clock_in_kecamatan', 'clock_in_kabupaten', 'clock_in_address',
        'clock_out_lat', 'clock_out_lng', 'clock_out_location_name',
        'clock_out_desa', 'clock_out_kecamatan', 'clock_out_kabupaten', 'clock_out_address',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'clock_in_lat' => 'float',
        'clock_in_lng' => 'float',
        'clock_out_lat' => 'float',
        'clock_out_lng' => 'float',
    ];

    protected $appends = [
        'clock_in_photo_url', 'clock_out_photo_url',
        'clock_in_maps_url', 'clock_out_maps_url',
        'clock_in_formatted_address', 'clock_out_formatted_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getClockInPhotoUrlAttribute(): ?string
    {
        return $this->clock_in_photo ? '/storage/'.ltrim($this->clock_in_photo, '/') : null;
    }

    public function getClockOutPhotoUrlAttribute(): ?string
    {
        return $this->clock_out_photo ? '/storage/'.ltrim($this->clock_out_photo, '/') : null;
    }

    public function getClockInMapsUrlAttribute(): ?string
    {
        if ($this->clock_in_lat && $this->clock_in_lng) {
            return "https://www.google.com/maps?q={$this->clock_in_lat},{$this->clock_in_lng}";
        }
        return null;
    }

    public function getClockOutMapsUrlAttribute(): ?string
    {
        if ($this->clock_out_lat && $this->clock_out_lng) {
            return "https://www.google.com/maps?q={$this->clock_out_lat},{$this->clock_out_lng}";
        }
        return null;
    }

    public function getClockInFormattedAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->clock_in_desa ? 'Desa '.$this->clock_in_desa : null,
            $this->clock_in_kecamatan ? 'Kec. '.$this->clock_in_kecamatan : null,
            $this->clock_in_kabupaten,
        ]);

        if (! empty($parts)) {
            return implode(', ', $parts);
        }

        return $this->clock_in_location_name ?: $this->clock_in_address;
    }

    public function getClockOutFormattedAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->clock_out_desa ? 'Desa '.$this->clock_out_desa : null,
            $this->clock_out_kecamatan ? 'Kec. '.$this->clock_out_kecamatan : null,
            $this->clock_out_kabupaten,
        ]);

        if (! empty($parts)) {
            return implode(', ', $parts);
        }

        return $this->clock_out_location_name ?: $this->clock_out_address;
    }
}
