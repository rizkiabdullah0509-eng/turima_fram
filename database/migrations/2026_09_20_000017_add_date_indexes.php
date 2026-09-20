<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['start_date', 'end_date']);
        });
    }
};
