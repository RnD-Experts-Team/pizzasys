<?php

namespace App\Services\V1\Auth;

use App\Models\Employee;
use App\Services\AuthEvents\Concerns\RecordsOutboxEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeAuthService
{
    use RecordsOutboxEvents;

    public function login(
        int $employeeId,
        string $password,
        ?array $device = null,
        ?string $fcmToken = null,
        string $clientType = 'web',
        ?Request $request = null
    ): array {
        $employee = Employee::find($employeeId);

        if (!$employee || !Hash::check($password, $employee->password)) {
            throw new \Exception('Invalid credentials');
        }

        if (!$employee->active) {
            throw new \Exception('Account is inactive');
        }

        $tokenResult = $employee->createToken('employee-auth-token');
        $token = $tokenResult->plainTextToken;

        if ($clientType === 'mobile' || $device || $fcmToken) {
            $normalizedDevice = $this->upsertEmployeeDevice($employee, $device, $fcmToken);

            $this->emitEmployeeDeviceUpsertedEvent(
                employeeId: (int) $employee->id,
                device: $normalizedDevice,
                request: $request
            );
        }

        return [
            'employee' => $this->getEmployeeCompleteData($employee),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    private function emitEmployeeDeviceUpsertedEvent(int $employeeId, array $device, ?Request $request = null): void
    {
        $this->recordEvent('auth.v1.employee.device.upserted', [
            'employee_id' => $employeeId,
            'device' => [
                'device_id' => data_get($device, 'device_id'),
                'platform' => data_get($device, 'platform'),
                'model' => data_get($device, 'model'),
                'os_version' => data_get($device, 'os_version'),
                'app_version' => data_get($device, 'app_version'),
                'fcm_token' => data_get($device, 'fcm_token'),
            ],
        ], $request, [
            // Login is unauthenticated, so the factory would report the actor
            // as service_client; the actor is the employee logging in.
            'actor_type' => 'employee',
            'actor_employee_id' => $employeeId,
        ]);
    }

    protected function upsertEmployeeDevice(Employee $employee, ?array $device, ?string $fcmToken): array
    {
        $deviceId = data_get($device, 'device_id');

        $query = $employee->devices();

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        } else {
            $query->where('platform', data_get($device, 'platform'))
                ->where('model', data_get($device, 'model'));
        }

        $payload = [
            'device_id' => $deviceId,
            'platform' => data_get($device, 'platform'),
            'model' => data_get($device, 'model'),
            'os_version' => data_get($device, 'os_version'),
            'app_version' => data_get($device, 'app_version'),
            'last_seen_at' => now(),
        ];

        if ($fcmToken) {
            $payload['fcm_token'] = $fcmToken;
        }

        $payload = array_filter($payload, fn($v) => !is_null($v));

        $existing = $query->first();

        if ($existing) {
            $existing->update($payload);
            $existing->refresh();

            return [
                'device_id' => $existing->device_id,
                'platform' => $existing->platform,
                'model' => $existing->model,
                'os_version' => $existing->os_version,
                'app_version' => $existing->app_version,
                'fcm_token' => $existing->fcm_token,
            ];
        }

        $created = $employee->devices()->create($payload);
        $created->refresh();

        return [
            'device_id' => $created->device_id,
            'platform' => $created->platform,
            'model' => $created->model,
            'os_version' => $created->os_version,
            'app_version' => $created->app_version,
            'fcm_token' => $created->fcm_token,
        ];
    }

    public function getEmployeeCompleteData(Employee $employee): array
    {
        $employee->load(['roles.permissions', 'permissions', 'stores']);

        // Hiring-sourced store memberships (one row per store) — informational.
        $storeData = $employee->stores->map(function ($membership) {
            return [
                'store_number' => (string) $membership->store_number,
                'status' => $membership->status,
                'active' => (bool) $membership->active,
                'effective_date' => optional($membership->effective_date)?->toDateString(),
            ];
        })->values();

        return [
            'id' => (int) $employee->id,
            'first_name' => (string) $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => (string) $employee->last_name,
            'full_name' => $employee->full_name,
            'active' => (bool) $employee->active,
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,

            'global_roles' => $employee->roles->map(function ($role) {
                return [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name,
                    'guard_name' => (string) $role->guard_name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => (int) $permission->id,
                            'name' => (string) $permission->name,
                            'guard_name' => (string) $permission->guard_name
                        ];
                    })
                ];
            })->values(),

            'global_permissions' => $employee->permissions->map(function ($permission) {
                return [
                    'id' => (int) $permission->id,
                    'name' => (string) $permission->name,
                    'guard_name' => (string) $permission->guard_name
                ];
            })->values(),

            // Employees have no store-roles; all permissions are global.
            'all_permissions' => $this->getAllEmployeePermissions($employee),

            'stores' => $storeData,
        ];
    }

    public function getAllEmployeePermissions(Employee $employee): array
    {
        return $employee->getAllPermissions()->unique('id')->map(function ($permission) {
            return [
                'id' => (int) $permission->id,
                'name' => (string) $permission->name,
                'guard_name' => (string) $permission->guard_name
            ];
        })->values()->all();
    }
}
