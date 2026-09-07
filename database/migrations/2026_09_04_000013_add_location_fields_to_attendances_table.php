<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('clock_in_photo');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            $table->string('clock_in_desa')->nullable()->after('clock_in_lng');
            $table->string('clock_in_kecamatan')->nullable()->after('clock_in_desa');
            $table->string('clock_in_kabupaten')->nullable()->after('clock_in_kecamatan');
            $table->text('clock_in_address')->nullable()->after('clock_in_kabupaten');
            $table->string('clock_in_location_name')->nullable()->after('clock_in_address');

            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_out_photo');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            $table->string('clock_out_desa')->nullable()->after('clock_out_lng');
            $table->string('clock_out_kecamatan')->nullable()->after('clock_out_desa');
            $table->string('clock_out_kabupaten')->nullable()->after('clock_out_kecamatan');
            $table->text('clock_out_address')->nullable()->after('clock_out_kabupaten');
            $table->string('clock_out_location_name')->nullable()->after('clock_out_address');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_lat', 'clock_in_lng', 'clock_in_desa', 'clock_in_kecamatan',
                'clock_in_kabupaten', 'clock_in_address', 'clock_in_location_name',
                'clock_out_lat', 'clock_out_lng', 'clock_out_desa', 'clock_out_kecamatan',
                'clock_out_kabupaten', 'clock_out_address', 'clock_out_location_name',
            ]);
        });
    }
};
