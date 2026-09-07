<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\Schedule;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /** Manajer: semua permintaan cuti */
    public function index()
    {
        return LeaveRequest::with('user')->latest()->get();
    }

    /** Karyawan: permintaan cuti milik sendiri saja */
    public function mine(Request $request)
    {
        return LeaveRequest::where('user_id', $request->user()->id)->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? '(tanpa alasan)',
            'status' => 'pending',
        ]);

        Notice::create(['message' => "{$request->user()->name} mengajukan cuti {$data['start_date']} s/d {$data['end_date']}."]);

        return response()->json($leave, 201);
    }

    public function approve(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update(['status' => 'approved']);

        // Hapus jadwal yang bentrok dengan tanggal cuti
        Schedule::where('user_id', $leaveRequest->user_id)
            ->whereBetween('date', [$leaveRequest->start_date, $leaveRequest->end_date])
            ->delete();

        Notice::create(['message' => "Cuti {$leaveRequest->user->name} ({$leaveRequest->start_date->toDateString()} s/d {$leaveRequest->end_date->toDateString()}) disetujui."]);

        return response()->json($leaveRequest);
    }

    public function reject(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update(['status' => 'rejected']);

        Notice::create(['message' => "Cuti {$leaveRequest->user->name} ditolak."]);

        return response()->json($leaveRequest);
    }
}
