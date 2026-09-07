<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'date', 'title', 'status', 'photo', 'completed_at', 'sort_order',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? '/storage/'.ltrim($this->photo, '/') : null;
    }
}
