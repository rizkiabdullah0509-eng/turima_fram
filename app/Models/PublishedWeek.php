<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishedWeek extends Model
{
    protected $fillable = ['week_start'];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
    ];
}
