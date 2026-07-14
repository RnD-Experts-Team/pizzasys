<?php

namespace App\Services\V1\Employees;

use App\Models\Employee;
use App\Services\AuthEvents\Concerns\RecordsOutboxEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeManagementService
{
    use RecordsOutboxEvents;

    public function getAllEmployees(
        int $perPage = 15,
        ?string $search = null,
        ?string $storeNumber = null,
        ?bool $active = null
    ) {
        $query = Employee::query()->with(['roles', 'stores']);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($storeNumber !== null && $storeNumber !== '') {
            $query->whereHas('stores', function ($q) use ($storeNumber) {
                $q->where('store_number', $storeNumber);
            });
        }

        if ($active !== null) {
            $query->where('active', $active);
        }

        return $query->orderBy('last_name')->orderBy('first_name')->paginate($perPage);
    }

    public function getEmployeeDetails(Employee $employee): Employee
    {
        return $employee->load([
            'roles.permissions',
            'permissions',
            'stores',
            'devices',
        ]);
    }

    public function updatePassword(Employee $employee, string $password, ?Request $request = null): void
    {
        $employee->update(['password' => Hash::make($password)]);

        // Force re-login everywhere with the new password.
        $employee->tokens()->delete();

        $this->recordEvent('auth.v1.employee.password.updated', [
            'employee_id' => (int) $employee->id,
            'updated_at' => now()->utc()->toIso8601String(),
        ], $request);
    }
}
