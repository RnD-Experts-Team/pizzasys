<?php

namespace App\Services\V1\Employees;

use App\Models\EmployeeRoleStore;
use App\Services\AuthEvents\Concerns\RecordsOutboxEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class EmployeeRoleStoreService
{
    use RecordsOutboxEvents;

    public function assignEmployeeRoleStore(array $data, ?Request $request = null): EmployeeRoleStore
    {
        return DB::transaction(function () use ($data, $request) {

            $assignment = EmployeeRoleStore::create([
                'employee_id' => (int) $data['employee_id'],
                'role_id' => (int) $data['role_id'],
                'store_id' => (int) $data['store_id'],
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $role = $assignment->role;
            $store = $assignment->store;
            $this->recordEvent('auth.v1.assignment.employee_role_store.assigned', [
                'assignment' => [
                    'id' => $assignment->id,
                    'employee_id' => (int) $assignment->employee_id,
                    'role_id' => (int) $assignment->role_id,
                    'store_id' => (int) $assignment->store_id,
                    'metadata' => $assignment->metadata,
                    'is_active' => (bool) $assignment->is_active,
                    'role_name' => $role?->name,
                    'store_lc_id' => $store?->store_id,
                    'created_at' => optional($assignment->created_at)?->toIso8601String(),
                    'updated_at' => optional($assignment->updated_at)?->toIso8601String(),
                ],
            ], $request);

            return $assignment;
        });
    }

    public function removeEmployeeRoleStore(int $employeeId, int $roleId, int $storeId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($employeeId, $roleId, $storeId, $request) {

            $assignment = EmployeeRoleStore::where('employee_id', $employeeId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->first();

            $deleted = EmployeeRoleStore::where('employee_id', $employeeId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.assignment.employee_role_store.removed', [
                    'employee_id' => $employeeId,
                    'role_id' => $roleId,
                    'store_id' => $storeId,
                    'assignment_id' => $assignment?->id,
                    'removed_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return (bool) $deleted;
        });
    }

    public function toggleEmployeeRoleStore(int $employeeId, int $roleId, int $storeId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($employeeId, $roleId, $storeId, $request) {

            $assignment = EmployeeRoleStore::where('employee_id', $employeeId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->first();

            if (!$assignment) {
                return false;
            }

            $before = (bool) $assignment->is_active;
            $after = !$before;

            $assignment->update(['is_active' => $after]);

            $this->recordEvent('auth.v1.assignment.employee_role_store.toggled', [
                'assignment_id' => $assignment->id,
                'employee_id' => $employeeId,
                'role_id' => $roleId,
                'store_id' => $storeId,
                'before_is_active' => $before,
                'after_is_active' => $after,
                'toggled_at' => now()->utc()->toIso8601String(),
            ], $request);

            return true;
        });
    }

    public function getEmployeeRoleStoreAssignments(int $employeeId, ?int $storeId = null)
    {
        $query = EmployeeRoleStore::where('employee_id', $employeeId)->with(['role', 'store']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    public function getStoreRoleAssignments(int $storeId, ?int $roleId = null)
    {
        $query = EmployeeRoleStore::where('store_id', $storeId)->with(['employee', 'role']);

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        return $query->get();
    }

    public function bulkAssignEmployeeRoleStore(int $employeeId, array $assignments, ?Request $request = null): array
    {
        return DB::transaction(function () use ($employeeId, $assignments, $request) {

            $results = [];

            foreach ($assignments as $assignment) {
                $results[] = EmployeeRoleStore::create([
                    'employee_id' => $employeeId,
                    'role_id' => (int) $assignment['role_id'],
                    'store_id' => (int) $assignment['store_id'],
                    'metadata' => $assignment['metadata'] ?? null,
                    'is_active' => $assignment['is_active'] ?? true,
                ]);
            }

            $this->recordEvent('auth.v1.assignment.employee_role_store.bulk_assigned', [
                'employee_id' => $employeeId,
                'count' => count($results),
                'assignments' => array_map(function ($row) {
                    /** @var \App\Models\EmployeeRoleStore $row */
                    $role = $row->role;
                    $store = $row->store;
                    return [
                        'id' => $row->id,
                        'role_id' => (int) $row->role_id,
                        'store_id' => (int) $row->store_id,
                        'metadata' => $row->metadata,
                        'role_name' => $role?->name,
                        'store_lc_id' => $store?->store_id,
                        'is_active' => (bool) $row->is_active,
                        'created_at' => optional($row->created_at)?->toIso8601String(),
                    ];
                }, $results),
            ], $request);

            return $results;
        });
    }
}
