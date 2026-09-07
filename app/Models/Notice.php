<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $table = 'notices';

    protected $fillable = ['message', 'is_read'];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
