<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AppSetting;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Attendance::query()->latest('date');

        if (! $user->canManageTeamSchedule()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        return $query->get();
    }

    public function clockIn(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'photo' => ['required', 'image', 'max:5120'], // maks 5MB
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'desa' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'location_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $schedule = Schedule::with('shiftTemplate')
            ->where('user_id', $user->id)
            ->whereDate('date', $data['date'])
            ->first();

        if (! $schedule?->shiftTemplate) {
            return response()->json(['message' => 'Absen masuk hanya dapat dilakukan pada jadwal shift yang aktif.'], 422);
        }

        $graceMinutes = $this->lateClockInGraceMinutes();
        $shiftStart = Carbon::parse($data['date'].' '.$schedule->shiftTemplate->start_time);
        $clockInDeadline = $shiftStart->copy()->addMinutes($graceMinutes);

        if (now()->greaterThan($clockInDeadline)) {
            return response()->json([
                'message' => 'Absen masuk sudah ditutup. Shift dimulai pukul '.$shiftStart->format('H:i')
                    .' dan batas absen adalah '.$clockInDeadline->format('H:i').'.',
            ], 422);
        }

        $path = $request->file('photo')->store('attendance', 'public');

        $attributes = [
            'clock_in' => now(),
            'clock_in_photo' => $path,
            'clock_in_lat' => $request->input('latitude'),
            'clock_in_lng' => $request->input('longitude'),
            'clock_in_desa' => $request->input('desa'),
            'clock_in_kecamatan' => $request->input('kecamatan'),
            'clock_in_kabupaten' => $request->input('kabupaten'),
            'clock_in_address' => $request->input('address'),
            'clock_in_location_name' => $request->input('location_name'),
        ];

        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $data['date']],
            $attributes
        );

        Notice::create(['message' => "{$user->name} absen masuk pada {$data['date']}."]);

        return response()->json($attendance);
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'photo' => ['required', 'image', 'max:5120'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'desa' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'location_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $hasSchedule = Schedule::where('user_id', $user->id)
            ->whereDate('date', $data['date'])
            ->exists();

        if (! $hasSchedule) {
            return response()->json(['message' => 'Absen pulang hanya dapat dilakukan pada jadwal shift yang aktif.'], 422);
        }

        $path = $request->file('photo')->store('attendance', 'public');
        $attendance = Attendance::firstOrNew(['user_id' => $user->id, 'date' => $data['date']]);
        $attendance->clock_out = now();
        $attendance->clock_out_photo = $path;
        $attendance->clock_out_lat = $request->input('latitude');
        $attendance->clock_out_lng = $request->input('longitude');
        $attendance->clock_out_desa = $request->input('desa');
        $attendance->clock_out_kecamatan = $request->input('kecamatan');
        $attendance->clock_out_kabupaten = $request->input('kabupaten');
        $attendance->clock_out_address = $request->input('address');
        $attendance->clock_out_location_name = $request->input('location_name');
        $attendance->save();

        Notice::create(['message' => "{$user->name} absen pulang pada {$data['date']}."]);

        return response()->json($attendance);
    }

    /**
     * Peringatan pribadi untuk jadwal yang terlambat atau belum diabsen.
     * Tetap terbatas pada akun yang sedang login, termasuk karyawan yang
     * memperoleh akses kelola jadwal tim.
     */
    public function lateWarnings(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $from = now()->subDays(60)->toDateString();

        $schedules = Schedule::with('shiftTemplate')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $today])
            ->orderByDesc('date')
            ->get();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$from, $today])
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->date->toDateString());

        $leaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $from)
            ->get();

        $warnings = $schedules->map(function (Schedule $schedule) use ($attendances, $leaves, $today) {
            $date = $schedule->date->toDateString();
            $onLeave = $leaves->contains(fn (LeaveRequest $leave) => $date >= $leave->start_date->toDateString()
                && $date <= $leave->end_date->toDateString());

            if ($onLeave || ! $schedule->shiftTemplate) {
                return null;
            }

            $shiftStart = Carbon::parse($date.' '.$schedule->shiftTemplate->start_time);
            $attendance = $attendances->get($date);

            if ($attendance?->clock_in) {
                $lateMinutes = $shiftStart->diffInMinutes($attendance->clock_in, false);
                if ($lateMinutes <= 0) {
                    return null;
                }

                return [
                    'type' => 'late',
                    'date' => $date,
                    'shift' => $schedule->shiftTemplate->name,
                    'shift_start' => $shiftStart->format('H:i'),
                    'clock_in' => $attendance->clock_in->format('H:i'),
                    'late_minutes' => $lateMinutes,
                    'message' => 'Terlambat '.$lateMinutes.' menit',
                ];
            }

            if ($date < $today || ($date === $today && now()->greaterThan($shiftStart))) {
                $lateMinutes = max(0, $shiftStart->diffInMinutes(now(), false));

                return [
                    'type' => 'missing',
                    'date' => $date,
                    'shift' => $schedule->shiftTemplate->name,
                    'shift_start' => $shiftStart->format('H:i'),
                    'clock_in' => null,
                    'late_minutes' => $lateMinutes,
                    'message' => $date === $today ? 'Belum absen masuk' : 'Tidak ada absen masuk',
                ];
            }

            return null;
        })->filter()->values();

        return response()->json($warnings);
    }

    /** Batas absen masuk ditampilkan dan diatur khusus oleh manajer. */
    public function settings()
    {
        return response()->json([
            'late_clock_in_grace_minutes' => $this->lateClockInGraceMinutes(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'late_clock_in_grace_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'late_clock_in_grace_minutes'],
            ['value' => (string) $data['late_clock_in_grace_minutes']]
        );

        return response()->json([
            'late_clock_in_grace_minutes' => (int) $data['late_clock_in_grace_minutes'],
            'message' => 'Aturan batas absen masuk berhasil disimpan.',
        ]);
    }

    private function lateClockInGraceMinutes(): int
    {
        $value = AppSetting::where('key', 'late_clock_in_grace_minutes')->value('value');

        return max(0, min(60, is_numeric($value) ? (int) $value : 2));
    }
}
