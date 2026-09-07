<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Notice;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    /**
     * Daftar tugas pada satu tanggal.
     *
     * Secara bawaan, endpoint ini selalu mengembalikan tugas milik pengguna
     * yang sedang masuk. Halaman tugas tim harus meminta scope=team secara
     * eksplisit. Ini penting untuk karyawan yang diberi akses kelola jadwal:
     * mereka tetap tidak boleh mencoba menyelesaikan tugas milik rekan kerja.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'scope' => ['nullable', 'in:team'],
        ]);

        $query = Task::where('date', $data['date'])->orderBy('sort_order');

        $user = $request->user();
        if (($data['scope'] ?? null) === 'team') {
            abort_unless($user->canManageTeamSchedule(), 403);
        } else {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * Tugaskan sekaligus beberapa judul tugas (checklist) ke 1 karyawan atau "all".
     * Urutan array $titles hanya menentukan urutan tampilan daftar tugas.
     */
    public function storeBulk(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'assignee' => ['required'], // 'all' atau id user
            'titles' => ['required', 'array', 'min:1'],
            'titles.*' => ['required', 'string', 'max:255'],
        ]);

        $targets = $data['assignee'] === 'all'
            ? User::where('role', 'employee')->pluck('id')
            : collect([$data['assignee']]);

        foreach ($targets as $userId) {
            $maxOrder = Task::where('user_id', $userId)->where('date', $data['date'])->max('sort_order') ?? 0;
            foreach ($data['titles'] as $i => $title) {
                Task::create([
                    'user_id' => $userId,
                    'date' => $data['date'],
                    'title' => $title,
                    'status' => 'pending',
                    'sort_order' => $maxOrder + $i + 1,
                ]);
            }
        }

        $label = count($data['titles']) === 1 ? '"'.$data['titles'][0].'"' : count($data['titles']).' tugas';
        Notice::create(['message' => "{$label} diberikan untuk tanggal {$data['date']}."]);

        return response()->json(['message' => 'Tugas berhasil ditugaskan.'], 201);
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Tugas dihapus.']);
    }

    /** Ekspor daftar tugas tim pada tanggal tertentu ke file Excel (.xlsx). */
    public function exportExcel(Request $request): Response
    {
        [$date, $tasks] = $this->tasksForExport($request);
        $contents = TaskExportService::excel(
            $tasks,
            $date,
            $request->getSchemeAndHttpHost(),
            storage_path('app/public')
        );

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="daftar_tugas_'.$date.'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Ekspor daftar tugas tim pada tanggal tertentu ke file PDF. */
    public function exportPdf(Request $request): Response
    {
        [$date, $tasks] = $this->tasksForExport($request);
        $contents = TaskExportService::pdf($tasks, $date, $request->getSchemeAndHttpHost());

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="daftar_tugas_'.$date.'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Tandai selesai — foto wajib diunggah. Karyawan bebas memilih tugas
     * miliknya mana yang ingin diselesaikan lebih dahulu.
     */
    public function complete(Request $request, Task $task)
    {
        $request->validate(['photo' => ['required', 'image', 'max:5120']]);

        abort_unless($task->user_id === $request->user()->id, 403);

        $path = $request->file('photo')->store('tasks', 'public');

        $task->update([
            'status' => 'done',
            'photo' => $path,
            'completed_at' => now(),
        ]);

        Notice::create(['message' => "{$request->user()->name} menyelesaikan tugas \"{$task->title}\" dengan bukti foto."]);

        return response()->json($task);
    }

    public function undo(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $task->update(['status' => 'pending', 'photo' => null, 'completed_at' => null]);

        return response()->json($task);
    }

    private function tasksForExport(Request $request): array
    {
        $user = $request->user();
        abort_unless(
            $user->isManager() || ($user->isEmployee() && $user->can_manage_schedule),
            403
        );

        $data = $request->validate(['date' => ['required', 'date']]);
        $tasks = Task::with('user:id,name,position')
            ->whereDate('date', $data['date'])
            ->orderBy('user_id')
            ->orderBy('sort_order')
            ->get();

        $attendances = Attendance::whereDate('date', $data['date'])
            ->whereIn('user_id', $tasks->pluck('user_id')->unique())
            ->get()
            ->keyBy('user_id');

        $tasks->each(function (Task $task) use ($attendances) {
            $task->setRelation('attendance', $attendances->get($task->user_id));
        });

        return [$data['date'], $tasks];
    }
}
