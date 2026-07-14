<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Employee;
use App\Services\EventConsume\EventHandlerInterface;
use App\Services\EventConsume\Handlers\Concerns\ReplicatesEmployeeStores;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeCreatedHandler implements EventHandlerInterface
{
    use ReplicatesEmployeeStores;

    public function handle(array $event): void
    {
        $employeePayload = $this->extractEmployeePayload($event);

        $id = $this->resolveEmployeeId($event, $employeePayload);
        if ($id <= 0) {
            throw new \Exception('EmployeeCreatedHandler: missing/invalid employee id');
        }

        $firstName = $this->stringOrNull(data_get($employeePayload, 'first_name'));
        $lastName = $this->stringOrNull(data_get($employeePayload, 'last_name'));
        $middleName = $this->stringOrNull(data_get($employeePayload, 'middle_name'));

        if ($firstName === null || $lastName === null) {
            throw new \Exception('EmployeeCreatedHandler: missing first_name or last_name');
        }

        // All stores replicated (no franchise filter); status matched per store.
        $stores = $this->resolveStores($event, $employeePayload) ?? [];
        $statusHistories = $this->resolveStatusHistories($event, $employeePayload) ?? [];
        $memberships = $this->buildMembershipRows($stores, $statusHistories);

        // Active iff the employee is an active member of at least one store.
        $active = $this->anyActive($memberships);

        DB::transaction(function () use ($id, $firstName, $middleName, $lastName, $active, $memberships) {
            $employee = Employee::query()->find($id);

            if ($employee) {
                // Replays/redeliveries must never overwrite an admin-set password.
                $employee->update([
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'active' => $active,
                ]);
            } else {
                Employee::query()->create([
                    'id' => $id,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'active' => $active,
                    'password' => Hash::make((string) config('authz.employee_default_password', 'Password123')),
                ]);
            }

            $this->replaceMemberships($id, $memberships);
        });
    }
}
