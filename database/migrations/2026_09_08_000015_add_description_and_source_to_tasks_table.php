<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('source', 20)->default('web')->after('sort_order');
        });

        // Ubah tipe status menjadi VARCHAR agar fleksibel mendukung 'pending', 'in_progress', 'done'
        try {
            DB::statement("ALTER TABLE tasks MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // Abaikan jika driver database tidak mendukung statement ini (misal sqlite testing)
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['description', 'source']);
        });

        try {
            DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending', 'done') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            //
        }
    }
};
