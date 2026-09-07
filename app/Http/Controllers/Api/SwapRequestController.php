<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Schedule;
use App\Models\SwapRequest;
use Illuminate\Http\Request;

class SwapRequestController extends Controller
{
    /** Manajer: semua permintaan tukar shift */
    public function index()
    {
        return SwapRequest::with(['fromUser', 'toUser', 'shiftTemplate'])->latest()->get();
    }

    /** Karyawan: permintaan tukar shift yang melibatkan dirinya (pengaju atau tujuan) */
    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        return SwapRequest::with(['fromUser', 'toUser', 'shiftTemplate'])
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
            })
            ->latest()
            ->get();
    }

    /** Karyawan mengajukan tukar shift miliknya sendiri pada tanggal tertentu */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'to_user_id' => ['required', 'exists:users,id'],
        ]);

        $fromUser = $request->user();

        $schedule = Schedule::where('user_id', $fromUser->id)->where('date', $data['date'])->first();

        if (! $schedule) {
            return response()->json(['message' => 'Anda tidak memiliki shift pada tanggal ini.'], 422);
        }

        $swap = SwapRequest::create([
            'from_user_id' => $fromUser->id,
            'to_user_id' => $data['to_user_id'],
            'shift_template_id' => $schedule->shift_template_id,
            'date' => $data['date'],
            'status' => 'pending_peer',
        ]);

        $toUser = \App\Models\User::find($data['to_user_id']);
        Notice::create(['message' => "{$fromUser->name} minta tukar shift tanggal {$data['date']} dengan {$toUser?->name}."]);

        return response()->json($swap->load(['fromUser', 'toUser', 'shiftTemplate']), 201);
    }

    /** Rekan kerja (to_user) menerima/menolak permintaan */
    public function peerRespond(Request $request, SwapRequest $swapRequest)
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);

        abort_unless($swapRequest->to_user_id === $request->user()->id, 403);

        $swapRequest->update(['status' => $data['accept'] ? 'pending_manager' : 'rejected_peer']);

        return response()->json($swapRequest);
    }

    /** Manajer menyetujui/menolak final, lalu menukar kepemilikan shift */
    public function managerRespond(Request $request, SwapRequest $swapRequest)
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);

        if ($data['approve']) {
            Schedule::where('user_id', $swapRequest->from_user_id)->where('date', $swapRequest->date)->delete();
            Schedule::where('user_id', $swapRequest->to_user_id)->where('date', $swapRequest->date)->delete();
            Schedule::create([
                'user_id' => $swapRequest->to_user_id,
                'shift_template_id' => $swapRequest->shift_template_id,
                'date' => $swapRequest->date,
            ]);
            $swapRequest->update(['status' => 'approved']);
            Notice::create(['message' => "Tukar shift tanggal {$swapRequest->date->toDateString()} disetujui manajer."]);
        } else {
            $swapRequest->update(['status' => 'rejected_manager']);
            Notice::create(['message' => "Tukar shift tanggal {$swapRequest->date->toDateString()} ditolak manajer."]);
        }

        return response()->json($swapRequest);
    }
}
