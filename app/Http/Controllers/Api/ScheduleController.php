<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AppSetting;
use App\Models\LeaveRequest;
use App\Models\PublishedWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Services\TaskExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends Controller
{
    /**
     * Ambil data jadwal 1 minggu (Senin - Minggu) untuk semua karyawan,
     * dipakai untuk menampilkan grid jadwal.
     */
    public function week(Request $request)
    {
        $weekStart = Carbon::parse($request->query('week_start', now()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $employees = User::where('role', 'employee')->orderBy('name')->get();

        $schedulesQuery = Schedule::with('shiftTemplate')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        $leavesQuery = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString());

        $attendancesQuery = Attendance::whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        $user = $request->user();
        if (! $user->canManageTeamSchedule()) {
            // Karyawan biasa hanya boleh melihat jadwal/cuti/absensi miliknya sendiri,
            // meski nama & posisi rekan kerja tetap terlihat di kolom karyawan.
            $schedulesQuery->where('user_id', $user->id);
            $leavesQuery->where('user_id', $user->id);
            $attendancesQuery->where('user_id', $user->id);
        }

        $schedules = $schedulesQuery->get();
        $leaves = $leavesQuery->get();
        $attendances = $attendancesQuery->get();

        $published = PublishedWeek::where('week_start', $weekStart->toDateString())->exists();
        $lateClockInGraceMinutes = AppSetting::where('key', 'late_clock_in_grace_minutes')->value('value');
        $lateClockInGraceMinutes = is_numeric($lateClockInGraceMinutes)
            ? max(0, min(60, (int) $lateClockInGraceMinutes))
            : 2;
        $serverNow = now();

        $schedules->each(function (Schedule $schedule) use ($lateClockInGraceMinutes, $serverNow) {
            if (! $schedule->shiftTemplate) {
                return;
            }

            $deadline = Carbon::parse($schedule->date->toDateString().' '.$schedule->shiftTemplate->start_time)
                ->addMinutes($lateClockInGraceMinutes);

            $schedule->setAttribute('clock_in_deadline', $deadline->toIso8601String());
            $schedule->setAttribute('clock_in_closed', $serverNow->greaterThan($deadline));
        });

        return response()->json([
            'today' => $serverNow->toDateString(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'published' => $published,
            'employees' => $employees,
            'schedules' => $schedules,
            'leaves' => $leaves,
            'attendances' => $attendances,
            'late_clock_in_grace_minutes' => $lateClockInGraceMinutes,
        ]);
    }

    /** Tugaskan / kosongkan shift pada tanggal tertentu untuk 1 karyawan */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'shift_template_id' => ['nullable', 'exists:shift_templates,id'],
        ]);

        if (empty($data['shift_template_id'])) {
            Schedule::where('user_id', $data['user_id'])->where('date', $data['date'])->delete();

            return response()->json(['message' => 'Slot dikosongkan.']);
        }

        // Cegah menjadwalkan karyawan yang sedang cuti disetujui pada tanggal itu
        $onLeave = LeaveRequest::where('user_id', $data['user_id'])
            ->where('status', 'approved')
            ->where('start_date', '<=', $data['date'])
            ->where('end_date', '>=', $data['date'])
            ->exists();

        if ($onLeave) {
            return response()->json(['message' => 'Karyawan sedang cuti pada tanggal ini.'], 422);
        }

        // Cegah double booking: karyawan sudah punya shift lain di hari yang sama
        $existingSchedule = Schedule::where('user_id', $data['user_id'])
            ->where('date', $data['date'])
            ->where('shift_template_id', '!=', $data['shift_template_id'])
            ->first();

        if ($existingSchedule) {
            return response()->json([
                'message' => 'Karyawan sudah memiliki shift "'.$existingSchedule->shiftTemplate->name.'" pada tanggal ini. Hapus shift sebelumnya atau pilih "Kosongkan slot" terlebih dahulu.',
            ], 422);
        }

        $schedule = Schedule::updateOrCreate(
            ['user_id' => $data['user_id'], 'date' => $data['date']],
            ['shift_template_id' => $data['shift_template_id']]
        );

        return response()->json($schedule->load('shiftTemplate'));
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['week_start' => ['required', 'date']]);
        $weekStart = $this->normalizeWeekStart($data['week_start']);

        PublishedWeek::firstOrCreate(['week_start' => $weekStart]);

        return response()->json(['message' => 'Jadwal dipublikasikan.']);
    }

    public function unpublish(Request $request)
    {
        $data = $request->validate(['week_start' => ['required', 'date']]);
        $weekStart = $this->normalizeWeekStart($data['week_start']);

        PublishedWeek::where('week_start', $weekStart)->delete();

        return response()->json(['message' => 'Publikasi dibatalkan.']);
    }

    /** Ekspor CSV: Karyawan, Posisi, Hari, Tanggal, Shift, Status Kehadiran (termasuk Cuti) */
    public function exportCsv(Request $request): StreamedResponse
    {
        $weekStart = Carbon::parse($request->query('week_start', now()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $today = now()->toDateString();

        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $schedules = Schedule::with('shiftTemplate')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])->get();
        $leaves = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())->get();
        $attendances = Attendance::whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])->get();

        $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $filename = 'jadwal_'.$weekStart->toDateString().'.csv';

        $callback = function () use ($employees, $schedules, $leaves, $attendances, $weekStart, $dayNames, $today) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Karyawan', 'Posisi', 'Hari', 'Tanggal', 'Shift', 'Status Kehadiran']);

            foreach ($employees as $emp) {
                for ($i = 0; $i < 7; $i++) {
                    $date = $weekStart->copy()->addDays($i)->toDateString();

                    $leave = $leaves->first(fn ($l) => $l->user_id === $emp->id && $date >= $l->start_date->toDateString() && $date <= $l->end_date->toDateString());
                    $schedule = $schedules->first(fn ($s) => $s->user_id === $emp->id && $s->date->toDateString() === $date);

                    $dateFormatted = $weekStart->copy()->addDays($i)->format('d-m-Y');

                    if ($leave) {
                        fputcsv($out, [$emp->name, $emp->position, $dayNames[$i], $dateFormatted, $schedule?->shiftTemplate?->name ?? '-', 'Cuti']);
                    } elseif ($schedule) {
                        $att = $attendances->first(fn ($a) => $a->user_id === $emp->id && $a->date->toDateString() === $date);
                        if ($date > $today) {
                            $status = 'Belum Waktunya';
                        } elseif ($att && $att->clock_in) {
                            $status = 'Hadir';
                        } else {
                            $status = 'Tidak Hadir';
                        }
                        fputcsv($out, [$emp->name, $emp->position, $dayNames[$i], $dateFormatted, $schedule->shiftTemplate->name, $status]);
                    }
                }
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /** Ekspor jadwal manajer dalam Excel dengan jam absen masuk dan pulang. */
    public function exportExcel(Request $request): Response
    {
        $weekStart = Carbon::parse($request->query('week_start', now()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $today = now()->toDateString();

        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $schedules = Schedule::with('shiftTemplate')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();
        $leaves = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->get();
        $attendances = Attendance::whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])->get();
        $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $rows = [];
        $number = 1;
        foreach ($employees as $employee) {
            for ($i = 0; $i < 7; $i++) {
                $date = $weekStart->copy()->addDays($i)->toDateString();
                $leave = $leaves->first(fn ($item) => $item->user_id === $employee->id
                    && $date >= $item->start_date->toDateString()
                    && $date <= $item->end_date->toDateString());
                $schedule = $schedules->first(fn ($item) => $item->user_id === $employee->id
                    && $item->date->toDateString() === $date);

                if (! $leave && ! $schedule) {
                    continue;
                }

                $attendance = $attendances->first(fn ($item) => $item->user_id === $employee->id
                    && $item->date->toDateString() === $date);
                $status = $leave ? 'Cuti' : ($date > $today
                    ? 'Belum Waktunya'
                    : ($attendance?->clock_in ? ($attendance->clock_out ? 'Hadir' : 'Masih bekerja') : 'Belum absen'));

                $rows[] = [
                    'no' => (string) $number++,
                    'employee' => $employee->name,
                    'position' => $employee->position ?: '-',
                    'day' => $dayNames[$i],
                    'date' => $weekStart->copy()->addDays($i)->format('d-m-Y'),
                    'shift' => $schedule?->shiftTemplate?->name ?? '-',
                    'attendance_status' => $status,
                    'clock_in' => $attendance?->clock_in?->format('H:i') ?? '-',
                    'clock_out' => $attendance?->clock_out?->format('H:i') ?? '-',
                ];
            }
        }

        $contents = TaskExportService::scheduleExcel($rows, $weekStart->toDateString());

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="jadwal_absensi_'.$weekStart->toDateString().'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * A schedule always starts on Monday. Older clients in time zones ahead
     * of UTC submitted Sunday for a locally selected Monday, so preserve that
     * intended week while accepting those existing requests.
     */
    private function normalizeWeekStart(string $value): string
    {
        $date = Carbon::parse($value)->startOfDay();

        return ($date->isSunday() ? $date->addDay() : $date->startOfWeek(Carbon::MONDAY))
            ->toDateString();
    }
}
