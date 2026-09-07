<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique(); // tanggal Senin dari minggu tsb
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_weeks');
    }
};
