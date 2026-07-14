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
        $employee->load(['roles.permissions', 'permissions']);

        $employeeStores = $employee->stores()->wherePivot('is_active', true)->get();

        $storeData = [];
        foreach ($employeeStores as $store) {
            $storePk = (int) $store->id;

            $storeRoles = $employee->getRolesForStore($storePk);
            $effectiveRoles = $employee->getEffectiveRolesForStore($storePk);
            $effectivePermissions = $employee->getEffectivePermissionsForStore($storePk);

            $storeData[] = [
                'store' => [
                    'id' => (int) $store->id,
                    'store_id' => (string) $store->store_id,
                    'name' => $store->name,
                    'metadata' => $store->metadata,
                    'is_active' => (bool) $store->is_active
                ],
                'direct_roles' => $storeRoles->map(function ($role) {
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
                'effective_roles' => $effectiveRoles->map(function ($role) use ($storeRoles) {
                    $isDirectRole = $storeRoles->contains('id', $role->id);
                    return [
                        'id' => (int) $role->id,
                        'name' => (string) $role->name,
                        'guard_name' => (string) $role->guard_name,
                        'is_inherited' => !$isDirectRole,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => (int) $permission->id,
                                'name' => (string) $permission->name,
                                'guard_name' => (string) $permission->guard_name
                            ];
                        })
                    ];
                })->values(),
                'effective_permissions' => $effectivePermissions->map(function ($permission) {
                    return [
                        'id' => (int) $permission->id,
                        'name' => (string) $permission->name,
                        'guard_name' => (string) $permission->guard_name
                    ];
                })->values(),
            ];
        }

        return [
            'id' => (int) $employee->id,
            'first_name' => (string) $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => (string) $employee->last_name,
            'full_name' => $employee->full_name,
            'store_id' => (string) $employee->store_id,
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

            'all_permissions' => $this->getAllEmployeePermissions($employee),

            'stores' => $storeData,
        ];
    }

    public function getAllEmployeePermissions(Employee $employee): array
    {
        $allPermissions = collect();

        $allPermissions = $allPermissions->merge($employee->getAllPermissions());

        $employeeStores = $employee->stores()->wherePivot('is_active', true)->get();
        foreach ($employeeStores as $store) {
            $storePermissions = $employee->getEffectivePermissionsForStore((int) $store->id);
            $allPermissions = $allPermissions->merge($storePermissions);
        }

        return $allPermissions->unique('id')->map(function ($permission) {
            return [
                'id' => (int) $permission->id,
                'name' => (string) $permission->name,
                'guard_name' => (string) $permission->guard_name
            ];
        })->values()->all();
    }
}
