<?php

namespace App\Http\Controllers\Api\V1\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Roles\CreateRoleRequest;
use App\Http\Requests\Api\V1\Roles\UpdateRoleRequest;
use App\Http\Requests\Api\V1\Users\AssignPermissionsRequest;
use App\Services\V1\Roles\RoleManagementService;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleManagementController extends Controller
{
    public function __construct(
        private RoleManagementService $roleManagementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->roleManagementService->getAllRoles(
            $request->get('per_page', 15),
            $request->get('search')
        );

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleManagementService->createRole($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => ['role' => $role]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['role' => $role->load('permissions')]
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->roleManagementService->updateRole($role, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => ['role' => $updatedRole]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleManagementService->deleteRole($role);

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function assignPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->roleManagementService->assignPermissionsToRole($role, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions assigned to role successfully',
                'data' => ['role' => $updatedRole]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions to role',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function revokePermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->roleManagementService->removePermissionsFromRole($role, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions revoked from role successfully',
                'data' => ['role' => $updatedRole]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke permissions from role',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function syncPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->roleManagementService->syncRolePermissions($role, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Role permissions synced successfully',
                'data' => ['role' => $updatedRole]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync role permissions',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
