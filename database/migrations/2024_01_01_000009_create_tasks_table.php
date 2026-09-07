<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->string('photo')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0); // urutan pengerjaan (satu per satu)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
