<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ShiftTemplateController;
use App\Http\Controllers\Api\SwapRequestController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskImportController;
use App\Http\Controllers\Api\TaskTemplateController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - TURIMA FRAM
|--------------------------------------------------------------------------
| Semua route di bawah ini otomatis diprefix /api (lihat RouteServiceProvider)
*/

Route::post('/login', [AuthController::class, 'login']);

// WhatsApp Bot Webhook & Status (WAHA integration)
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
Route::get('/whatsapp/status', [WhatsAppWebhookController::class, 'status']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Notifikasi (semua role bisa lihat)
    Route::get('/notices', [NoticeController::class, 'index']);
    Route::post('/notices/mark-all-read', [NoticeController::class, 'markAllRead']);

    // Jadwal & shift template - manajer, admin, dan karyawan dgn can_manage_schedule
    Route::middleware('role:manager,admin,employee')->group(function () {
        Route::get('/schedule/week', [ScheduleController::class, 'week']);
        Route::get('/shift-templates', [ShiftTemplateController::class, 'index']);
    });

    // Jadwal & tugas — manajer, admin, DAN karyawan dgn akses jadwal tim
    Route::middleware('role:manager,admin,schedule')->group(function () {
        Route::post('/schedule/assign', [ScheduleController::class, 'assign']);
        Route::post('/schedule/publish', [ScheduleController::class, 'publish']);
        Route::post('/schedule/unpublish', [ScheduleController::class, 'unpublish']);
        Route::get('/schedule/export-csv', [ScheduleController::class, 'exportCsv']);
        Route::get('/schedule/export-excel', [ScheduleController::class, 'exportExcel']);

        Route::post('/shift-templates', [ShiftTemplateController::class, 'store']);
        Route::put('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'update']);
        Route::delete('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'destroy']);

        Route::get('/task-templates', [TaskTemplateController::class, 'index']);
        Route::post('/task-templates', [TaskTemplateController::class, 'store']);
        Route::delete('/task-templates/{taskTemplate}', [TaskTemplateController::class, 'destroy']);

        Route::post('/tasks/bulk', [TaskController::class, 'storeBulk']);
        Route::get('/tasks/export-excel', [TaskController::class, 'exportExcel'])
            ->middleware('role:manager,schedule');
        Route::get('/tasks/export-pdf', [TaskController::class, 'exportPdf'])
            ->middleware('role:manager,schedule');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    });

    // Laporan — hanya manajer & admin
    Route::middleware('role:manager,admin')->group(function () {
        Route::get('/reports/weekly-hours', [ReportController::class, 'weeklyHours']);
    });

    // Daftar karyawan (baca saja) — manajer, admin, dan karyawan dgn akses jadwal
    Route::middleware('role:manager,admin,schedule')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
    });

    // Manajemen karyawan & keputusan — khusus manajer
    Route::middleware('role:manager')->group(function () {
        Route::get('/tasks/import-template', [TaskImportController::class, 'template']);
        Route::post('/tasks/import-excel', [TaskImportController::class, 'store']);

        Route::get('/attendance/settings', [AttendanceController::class, 'settings']);
        Route::put('/attendance/settings', [AttendanceController::class, 'updateSettings']);

        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::post('/employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword']);
        Route::post('/employees/{employee}/toggle-schedule-access', [EmployeeController::class, 'toggleScheduleAccess']);

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);

        Route::get('/swap-requests', [SwapRequestController::class, 'index']);
        Route::post('/swap-requests/{swapRequest}/manager-respond', [SwapRequestController::class, 'managerRespond']);
    });

    // Endpoint milik karyawan sendiri (semua role login boleh akses miliknya)
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{task}/undo', [TaskController::class, 'undo']);

    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/late-warnings', [AttendanceController::class, 'lateWarnings']);

    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/mine', [LeaveRequestController::class, 'mine']);

    Route::post('/swap-requests', [SwapRequestController::class, 'store']);
    Route::get('/swap-requests/mine', [SwapRequestController::class, 'mine']);
    Route::post('/swap-requests/{swapRequest}/peer-respond', [SwapRequestController::class, 'peerRespond']);
});
