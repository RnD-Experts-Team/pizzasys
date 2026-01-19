<?php

namespace App\Http\Controllers\Api\V1\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Permissions\CreatePermissionRequest;
use App\Http\Requests\Api\V1\Permissions\UpdatePermissionRequest;
use App\Services\V1\Permissions\PermissionManagementService;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionManagementController extends Controller
{
    public function __construct(
        private PermissionManagementService $permissionManagementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $permissions = $this->permissionManagementService->getAllPermissions(
            $request->get('per_page', 15),
            $request->get('search')
        );

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    public function store(CreatePermissionRequest $request): JsonResponse
    {
        try {
            $permission = $this->permissionManagementService->createPermission($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => ['permission' => $permission]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['permission' => $permission->load('roles')]
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        try {
            $updatedPermission = $this->permissionManagementService->updatePermission($permission, $request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => ['permission' => $updatedPermission]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        try {
            $this->permissionManagementService->deletePermission($permission, $request);

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
