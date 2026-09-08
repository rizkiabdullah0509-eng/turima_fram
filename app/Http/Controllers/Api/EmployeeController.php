<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        return User::where('role', 'employee')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'max_hours' => ['nullable', 'integer', 'min:1', 'max:80'],
            'username' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:4'],
            'can_manage_schedule' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $username = $this->uniqueUsername($data['username'] ?? $data['name']);

        $employee = User::create([
            'name' => $data['name'],
            'username' => $username,
            'role' => 'employee',
            'position' => $data['position'] ?? 'Staf',
            'max_hours' => $data['max_hours'] ?? 40,
            'password' => Hash::make($data['password'] ?? '12345'),
            'can_manage_schedule' => $data['can_manage_schedule'] ?? false,
            'phone' => !empty($data['phone']) ? User::normalizePhoneNumber($data['phone']) : null,
        ]);

        return response()->json($employee, 201);
    }

    public function update(Request $request, User $employee)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'max_hours' => ['sometimes', 'integer', 'min:1', 'max:80'],
            'username' => ['sometimes', 'string', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($employee->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
            'can_manage_schedule' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        abort_unless($employee->isEmployee(), 404);

        if (array_key_exists('password', $data)) {
            if ($data['password'] === null || $data['password'] === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = !empty($data['phone']) ? User::normalizePhoneNumber($data['phone']) : null;
        }

        $employee->update($data);

        return response()->json($employee);
    }

    public function destroy(User $employee)
    {
        $employee->delete();

        return response()->json(['message' => 'Karyawan dihapus, jadwal terkait juga dihapus.']);
    }

    /** Reset kata sandi karyawan ke default (12345) */
    public function resetPassword(User $employee)
    {
        $employee->update(['password' => Hash::make('12345')]);

        return response()->json(['message' => 'Kata sandi direset ke 12345.']);
    }

    /** Nyalakan/matikan akses "Jadwal Tim" & "Tugas Tim" untuk karyawan ini */
    public function toggleScheduleAccess(User $employee)
    {
        $employee->update(['can_manage_schedule' => ! $employee->can_manage_schedule]);

        return response()->json($employee);
    }

    private function uniqueUsername(string $base): string
    {
        $slug = Str::slug(Str::before($base, ' '), '');
        $slug = $slug ?: 'user';
        $candidate = $slug;
        $n = 1;
        while (User::where('username', $candidate)->exists()) {
            $n++;
            $candidate = $slug.$n;
        }

        return $candidate;
    }
}
