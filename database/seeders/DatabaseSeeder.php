<?php

namespace Database\Seeders;

use App\Models\ShiftTemplate;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Akun Manajer & Admin ----------
        User::create([
            'name' => 'Admin Manajer',
            'username' => 'manajer',
            'password' => Hash::make('admin123'),
            'role' => 'manager',
        ]);

        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        // ---------- Karyawan Contoh ----------
        $employees = [
            ['name' => 'Dewi Anggraini', 'username' => 'dewi', 'position' => 'Kasir', 'max_hours' => 40],
            ['name' => 'Budi Santoso', 'username' => 'budi', 'position' => 'Barista', 'max_hours' => 40],
            ['name' => 'Rina Wijaya', 'username' => 'rina', 'position' => 'Pelayan', 'max_hours' => 35],
            ['name' => 'Andi Pratama', 'username' => 'andi', 'position' => 'Supervisor', 'max_hours' => 45, 'can_manage_schedule' => true],
            ['name' => 'Cici Paramida', 'username' => 'cici', 'position' => 'Pelayan', 'max_hours' => 40],
        ];

        foreach ($employees as $emp) {
            User::create([
                'name' => $emp['name'],
                'username' => $emp['username'],
                'password' => Hash::make('12345'),
                'role' => 'employee',
                'position' => $emp['position'],
                'max_hours' => $emp['max_hours'],
                'can_manage_schedule' => $emp['can_manage_schedule'] ?? false,
            ]);
        }

        // ---------- Template Shift ----------
        ShiftTemplate::create(['name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '16:00', 'color' => 'amber']);
        ShiftTemplate::create(['name' => 'Siang', 'start_time' => '12:00', 'end_time' => '20:00', 'color' => 'teal']);
        ShiftTemplate::create(['name' => 'Malam', 'start_time' => '16:00', 'end_time' => '00:00', 'color' => 'plum']);

        // ---------- Template Tugas Harian ----------
        foreach (['Bersih-bersih gudang', 'Nyabut rumput', 'Bersih-bersih parit', 'Ngasah arit'] as $title) {
            TaskTemplate::create(['title' => $title]);
        }
    }
}
