<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Employee;
use App\Models\EmployeeStore;
use App\Services\EventConsume\EventHandlerInterface;
use App\Services\EventConsume\Handlers\Concerns\ReplicatesEmployeeStores;
use Illuminate\Support\Facades\DB;

/**
 * The hiring `updated` event carries only a delta (data.changed_fields), not a
 * full snapshot. We recompute store memberships when stores and/or status
 * histories are in the delta; otherwise memberships are left untouched.
 */
class EmployeeUpdatedHandler implements EventHandlerInterface
{
    use ReplicatesEmployeeStores;

    public function handle(array $event): void
    {
        $employeePayload = $this->extractEmployeePayload($event);

        $id = $this->resolveEmployeeId($event, $employeePayload);
        if ($id <= 0) {
            throw new \Exception('EmployeeUpdatedHandler: missing/invalid employee id');
        }

        DB::transaction(function () use ($id, $event, $employeePayload) {
            $employee = Employee::query()->whereKey($id)->lockForUpdate()->first();
            if (!$employee) {
                throw new \Exception("EmployeeUpdatedHandler: employee {$id} not synced yet");
            }

            $update = [];

            $firstName = $this->firstChangedOrPayload($event, $employeePayload, 'first_name');
            if ($firstName !== null) {
                $update['first_name'] = $firstName;
            }

            if ($this->hasChangedField($event, 'middle_name')) {
                $update['middle_name'] = $this->firstChangedOrPayload($event, $employeePayload, 'middle_name');
            }

            $lastName = $this->firstChangedOrPayload($event, $employeePayload, 'last_name');
            if ($lastName !== null) {
                $update['last_name'] = $lastName;
            }

            // Recompute memberships when stores and/or status are in the delta.
            $stores = $this->resolveStores($event, $employeePayload);
            $statusHistories = $this->resolveStatusHistories($event, $employeePayload);
            $memberships = $this->resolveMembershipsForUpdate($id, $stores, $statusHistories);

            if ($memberships !== null) {
                $this->replaceMemberships($id, $memberships);
                $update['active'] = $this->anyActive($memberships);
            }

            $wasActive = (bool) $employee->active;

            // Never touch password here — hiring events don't carry credentials.
            if (!empty($update)) {
                DB::table('employees')->where('id', $id)->update($update);
            }

            $isActive = array_key_exists('active', $update) ? (bool) $update['active'] : $wasActive;

            if ($wasActive && !$isActive) {
                // Deactivated → revoke every issued token so live sessions end.
                $employee->tokens()->delete();
            }
        });
    }

    /**
     * Determine the new membership set for an update, or null to leave memberships as-is.
     *
     * @param  array<int, array>|null  $stores
     * @param  array<int, array>|null  $statusHistories
     * @return array<int, array>|null
     */
    private function resolveMembershipsForUpdate(int $id, ?array $stores, ?array $statusHistories): ?array
    {
        // Stores changed → full rebuild (status fallback keeps prior per-store status).
        if ($stores !== null) {
            return $this->buildMembershipRows($stores, $statusHistories ?? [], $this->existingStatusByStore($id));
        }

        // Only status changed → keep existing store list, recompute status per store.
        if ($statusHistories !== null) {
            $existing = EmployeeStore::query()->where('employee_id', $id)->get();
            $syntheticStores = $existing->map(fn ($row) => [
                'store' => ['store_number' => $row->store_number],
                'effective_date' => optional($row->effective_date)?->toDateString(),
            ])->all();

            return $this->buildMembershipRows(
                $syntheticStores,
                $statusHistories,
                $existing->pluck('status', 'store_number')->all()
            );
        }

        // Neither stores nor status in the delta → leave memberships untouched.
        return null;
    }

    private function changedFields(array $event): array
    {
        $changed = data_get($event, 'data.changed_fields');
        return is_array($changed) ? $changed : [];
    }

    private function hasChangedField(array $event, string $field): bool
    {
        return array_key_exists($field, $this->changedFields($event));
    }

    private function firstChangedOrPayload(array $event, array $employeePayload, string $field): ?string
    {
        $changed = $this->changedFields($event);

        if (array_key_exists($field, $changed)) {
            return $this->extractDeltaToString($changed[$field]);
        }

        return $this->stringOrNull(data_get($employeePayload, $field));
    }

    private function extractDeltaToString(mixed $value): ?string
    {
        if (is_array($value) && array_key_exists('to', $value)) {
            return $this->stringOrNull($value['to']);
        }

        return $this->stringOrNull($value);
    }
}
