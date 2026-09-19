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

            // Hitung keterlambatan: bandingkan clock_in dengan jam mulai shift
            $totalLateMinutes = 0;
            $lateCount = 0;
            $empSchedules = $schedules->where('user_id', $emp->id);

            foreach ($empSchedules as $schedule) {
                if (! $schedule->shiftTemplate) {
                    continue;
                }

                $date = $schedule->date->toDateString();
                $attendance = $attendances->first(fn ($a) => $a->user_id === $emp->id && $a->date->toDateString() === $date);

                if ($attendance?->clock_in) {
                    $shiftStart = Carbon::parse($date.' '.$schedule->shiftTemplate->start_time);
                    $lateMinutes = max(0, $shiftStart->diffInMinutes($attendance->clock_in, false));

                    if ($lateMinutes > 0) {
                        $totalLateMinutes += $lateMinutes;
                        $lateCount++;
                    }
                }
            }

            return [
                'employee' => $emp,
                'scheduled_hours' => round($scheduled, 1),
                'actual_hours' => round($actual, 1),
                'total_late_minutes' => $totalLateMinutes,
                'late_count' => $lateCount,
            ];
        });

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'rows' => $rows,
        ]);
    }
}
