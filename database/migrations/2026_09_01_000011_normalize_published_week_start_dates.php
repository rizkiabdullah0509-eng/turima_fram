<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correct publication rows created from a UTC-shifted date in the client.
     */
    public function up(): void
    {
        DB::table('published_weeks')->orderBy('id')->get()->each(function ($week) {
            $date = Carbon::parse($week->week_start)->startOfDay();
            $monday = ($date->isSunday() ? $date->addDay() : $date->startOfWeek(Carbon::MONDAY))
                ->toDateString();

            if ($week->week_start === $monday) {
                return;
            }

            $duplicateExists = DB::table('published_weeks')
                ->where('week_start', $monday)
                ->where('id', '!=', $week->id)
                ->exists();

            if ($duplicateExists) {
                DB::table('published_weeks')->where('id', $week->id)->delete();

                return;
            }

            DB::table('published_weeks')
                ->where('id', $week->id)
                ->update(['week_start' => $monday]);
        });
    }

    public function down(): void
    {
        // The original day was erroneous and cannot be reconstructed safely.
    }
};
