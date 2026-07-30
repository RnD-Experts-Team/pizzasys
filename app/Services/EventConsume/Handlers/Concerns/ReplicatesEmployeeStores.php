<?php

namespace App\Services\EventConsume\Handlers\Concerns;

use App\Models\EmployeeStore;

/**
 * Shared parsing for hiring employee events. The `created` event carries a full
 * `data.employee` snapshot; the `updated` event carries only
 * `data.changed_fields.<field>.{from,to}` deltas (full arrays for stores /
 * status_histories when they change).
 *
 * Store membership is sourced from `data.employee.stores[]`; the per-store
 * employment status is matched from `data.employee.status_histories[]` (each of
 * which carries the store it applies to).
 */
trait ReplicatesEmployeeStores
{
    /** Statuses that make a store membership (and thus the employee) active. */
    private const ACTIVE_STATUSES = ['hired', 'rehired', 'oje'];

    protected function extractEmployeePayload(array $event): array
    {
        $employee = data_get($event, 'data.employee');
        if (is_array($employee)) {
            return $employee;
        }

        $employee = data_get($event, 'employee');
        if (is_array($employee)) {
            return $employee;
        }

        return [];
    }

    protected function resolveEmployeeId(array $event, array $employeePayload): int
    {
        $id = $this->asInt(data_get($employeePayload, 'id'));
        if ($id > 0) {
            return $id;
        }

        return $this->asInt(
            data_get($event, 'data.employee_id')
            ?? data_get($event, 'employee_id')
        );
    }

    /**
     * Store assignment entries: created → data.employee.stores;
     * updated → data.changed_fields.stores.to. Null when the event doesn't
     * carry stores (updated event where stores didn't change).
     */
    protected function resolveStores(array $event, array $employeePayload): ?array
    {
        $stores = data_get($employeePayload, 'stores');
        if (is_array($stores)) {
            return $stores;
        }

        $stores = data_get($event, 'data.changed_fields.stores.to');
        if (is_array($stores)) {
            return $stores;
        }

        return null;
    }

    /**
     * Status history entries: created → data.employee.status_histories;
     * updated → data.changed_fields.status_histories.to. Null when absent.
     */
    protected function resolveStatusHistories(array $event, array $employeePayload): ?array
    {
        $statuses = data_get($employeePayload, 'status_histories');
        if (is_array($statuses)) {
            return $statuses;
        }

        $statuses = data_get($event, 'data.changed_fields.status_histories.to');
        if (is_array($statuses)) {
            return $statuses;
        }

        return null;
    }

    /**
     * Build one membership row per store, matching each store to its latest
     * status. Rows are keyed/deduped by store_number (newest kept — hiring
     * arrays are newest-first).
     *
     * @param  array<int, array>  $stores
     * @param  array<int, array>  $statusHistories
     * @param  array<string, string|null>  $existingStatusByStore  fallback status per store_number
     * @return array<int, array{store_number:string,status:?string,active:bool,effective_date:?string}>
     */
    protected function buildMembershipRows(array $stores, array $statusHistories, array $existingStatusByStore = []): array
    {
        $overallStatus = $this->latestOverallStatus($statusHistories);

        $rows = [];
        foreach ($stores as $store) {
            if (!is_array($store)) {
                continue;
            }

            $storeNumber = $this->stringOrNull(data_get($store, 'store.store_number'));
            if ($storeNumber === null || isset($rows[$storeNumber])) {
                continue;
            }

            $status = $this->resolveStatusForStore($storeNumber, $statusHistories);
            if ($status === null) {
                $status = $existingStatusByStore[$storeNumber] ?? $overallStatus;
            }

            $rows[$storeNumber] = [
                'store_number' => $storeNumber,
                'status' => $status,
                'active' => $this->isActiveStatus($status),
                'effective_date' => $this->stringOrNull(data_get($store, 'effective_date')),
            ];
        }

        return array_values($rows);
    }

    protected function isActiveStatus(?string $status): bool
    {
        return $status !== null && in_array(strtolower($status), self::ACTIVE_STATUSES, true);
    }

    /**
     * Replace the employee's store memberships with the given set (delete + insert).
     *
     * @param  array<int, array{store_number:string,status:?string,active:bool,effective_date:?string}>  $memberships
     */
    protected function replaceMemberships(int $employeeId, array $memberships): void
    {
        EmployeeStore::query()->where('employee_id', $employeeId)->delete();

        foreach ($memberships as $m) {
            EmployeeStore::query()->create([
                'employee_id' => $employeeId,
                'store_number' => $m['store_number'],
                'status' => $m['status'],
                'active' => $m['active'],
                'effective_date' => $m['effective_date'],
            ]);
        }
    }

    /**
     * @param  array<int, array{active:bool}>  $memberships
     */
    protected function anyActive(array $memberships): bool
    {
        foreach ($memberships as $m) {
            if (!empty($m['active'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Current memberships keyed by store_number → status (for update fallback).
     *
     * @return array<string, string|null>
     */
    protected function existingStatusByStore(int $employeeId): array
    {
        return EmployeeStore::query()
            ->where('employee_id', $employeeId)
            ->pluck('status', 'store_number')
            ->all();
    }

    /** Latest status among the histories that apply to a given store. */
    private function resolveStatusForStore(string $storeNumber, array $statusHistories): ?string
    {
        $matching = array_filter($statusHistories, function ($entry) use ($storeNumber) {
            return is_array($entry)
                && $this->stringOrNull(data_get($entry, 'store.store_number')) === $storeNumber;
        });

        $latest = $this->latestEntry(array_values($matching));

        return $this->stringOrNull(data_get($latest, 'status'));
    }

    private function latestOverallStatus(array $statusHistories): ?string
    {
        $latest = $this->latestEntry($statusHistories);

        return $this->stringOrNull(data_get($latest, 'status'));
    }

    protected function latestEntry(array $items): ?array
    {
        $latest = null;
        $latestTs = null;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ts = $this->timestampFromEntry($item);

            if ($latest === null || ($ts !== null && ($latestTs === null || $ts > $latestTs))) {
                $latest = $item;
                $latestTs = $ts;
            }
        }

        return $latest;
    }

    private function timestampFromEntry(array $entry): ?int
    {
        $candidates = [
            data_get($entry, 'effective_date'),
            data_get($entry, 'created_at'),
            data_get($entry, 'updated_at'),
        ];

        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $ts = strtotime($value);
            if ($ts !== false) {
                return $ts;
            }
        }

        return null;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function asInt(mixed $v): int
    {
        if (is_int($v)) {
            return $v;
        }

        if (is_string($v) && ctype_digit($v)) {
            return (int) $v;
        }

        if (is_numeric($v)) {
            return (int) $v;
        }

        return 0;
    }
}
