<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function weeklyHours(Request $request)
    {
        $weekStart = Carbon::parse($request->query('week_start', now()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $schedules = Schedule::with('shiftTemplate')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])->get();
        $attendances = Attendance::whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])->get();

        $rows = $employees->map(function ($emp) use ($schedules, $attendances) {
            $scheduled = $schedules->where('user_id', $emp->id)->sum(fn ($s) => $s->shiftTemplate?->durationHours() ?? 0);

            $actual = $attendances->where('user_id', $emp->id)->sum(function ($a) {
                if ($a->clock_in && $a->clock_out) {
                    return round($a->clock_in->diffInMinutes($a->clock_out) / 60, 1);
                }
                return 0;
            });

            return [
                'employee' => $emp,
                'scheduled_hours' => round($scheduled, 1),
                'actual_hours' => round($actual, 1),
            ];
        });

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'rows' => $rows,
        ]);
    }
}
