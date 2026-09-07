<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskExportService;
use App\Services\TaskImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TaskImportController extends Controller
{
    public function template(): Response
    {
        return response(TaskImportService::template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_impor_tugas_harian.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        try {
            $rows = TaskImportService::read($data['file']->getRealPath());
        } catch (RuntimeException $exception) {
            return response(['message' => $exception->getMessage()], 422);
        }

        if ($rows === []) {
            return response(['message' => 'Tidak ada tugas untuk diimpor. Isi data mulai baris ketiga pada template.'], 422);
        }

        $employees = User::where('role', 'employee')->get()
            ->keyBy(fn (User $user) => Str::lower($user->username));
        $errors = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowLabel = 'Baris '.$row['row'];
            $employee = $employees->get(Str::lower($row['username']));
            if (! $employee) {
                $errors[] = $rowLabel.': username karyawan “'.$row['username'].'” tidak ditemukan.';
                continue;
            }

            try {
                $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])
                    ? Carbon::createFromFormat('!Y-m-d', $row['date'])
                    : null;
                $validDate = $date && $date->format('Y-m-d') === $row['date'];
            } catch (\Throwable) {
                $validDate = false;
            }

            if (! $validDate) {
                $errors[] = $rowLabel.': tanggal harus memakai format YYYY-MM-DD.';
                continue;
            }

            if ($row['title'] === '' || mb_strlen($row['title']) > 255) {
                $errors[] = $rowLabel.': tugas wajib diisi dan maksimal 255 karakter.';
                continue;
            }

            $order = null;
            if (isset($row['order']) && $row['order'] !== null && $row['order'] !== '') {
                if (! is_numeric($row['order']) || (int) $row['order'] < 1) {
                    $errors[] = $rowLabel.': urutan harus berupa angka positif (contoh: 1, 2, 3).';
                    continue;
                }
                $order = (int) $row['order'];
            }

            $prepared[] = [
                'user_id' => $employee->id,
                'date' => $row['date'],
                'title' => $row['title'],
                'order' => $order,
                'source_row' => $row['row'],
            ];
        }

        if ($errors !== []) {
            return response(['message' => 'Impor dibatalkan. '.implode(' ', array_slice($errors, 0, 5))], 422);
        }

        $created = DB::transaction(function () use ($prepared) {
            $grouped = [];
            foreach ($prepared as $item) {
                $key = $item['user_id'].'|'.$item['date'];
                $grouped[$key][] = $item;
            }

            $count = 0;
            foreach ($grouped as $key => $items) {
                [$userId, $taskDate] = explode('|', $key);

                usort($items, function ($a, $b) {
                    $orderA = $a['order'] ?? PHP_INT_MAX;
                    $orderB = $b['order'] ?? PHP_INT_MAX;
                    if ($orderA !== $orderB) {
                        return $orderA <=> $orderB;
                    }

                    return $a['source_row'] <=> $b['source_row'];
                });

                $maxExisting = Task::where('user_id', $userId)
                    ->whereDate('date', $taskDate)
                    ->max('sort_order') ?? 0;

                $lastAssigned = $maxExisting;
                foreach ($items as $item) {
                    if ($item['order'] !== null) {
                        $candidate = $maxExisting > 0 ? ($maxExisting + $item['order']) : $item['order'];
                        $assignedOrder = max($candidate, $lastAssigned + 1);
                    } else {
                        $assignedOrder = $lastAssigned + 1;
                    }
                    $lastAssigned = $assignedOrder;

                    Task::create([
                        'user_id' => $item['user_id'],
                        'date' => $item['date'],
                        'title' => $item['title'],
                        'status' => 'pending',
                        'sort_order' => $assignedOrder,
                    ]);
                    $count++;
                }
            }

            return $count;
        });

        Notice::create(['message' => $created.' tugas harian berhasil diimpor dari Excel.']);

        return response(['message' => $created.' tugas berhasil diimpor dan langsung masuk ke tugas harian karyawan.', 'count' => $created], 201);
    }
}
