<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwapRequest extends Model
{
    protected $fillable = ['from_user_id', 'to_user_id', 'shift_template_id', 'date', 'status'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function shiftTemplate()
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
}
