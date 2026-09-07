<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->enum('role', ['manager', 'admin', 'employee'])->default('employee');
            $table->string('position')->nullable(); // posisi/jabatan, khusus role employee
            $table->unsignedInteger('max_hours')->default(40); // batas jam kerja/minggu
            $table->boolean('can_manage_schedule')->default(false); // akses "Jadwal Tim" & "Tugas Tim" untuk employee
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
