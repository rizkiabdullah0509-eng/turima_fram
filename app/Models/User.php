<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'position',
        'max_hours',
        'can_manage_schedule',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'can_manage_schedule' => 'boolean',
        'max_hours' => 'integer',
    ];

    // ---------- Helper role ----------
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /** Manajer, admin, atau karyawan dengan akses tambahan bisa kelola jadwal & tugas tim */
    public function canManageTeamSchedule(): bool
    {
        return $this->isManager() || $this->isAdmin() || ($this->isEmployee() && $this->can_manage_schedule);
    }

    // ---------- Relasi ----------
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
