<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\CreateUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Requests\Api\V1\Users\AssignRolesRequest;
use App\Http\Requests\Api\V1\Users\AssignPermissionsRequest;
use App\Services\V1\Users\UserManagementService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function __construct(
        private UserManagementService $userManagementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userManagementService->getAllUsers(
            $request->get('per_page', 15),
            $request->get('search'),
            $request->get('role')
        );

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userManagementService->createUser($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => ['user' => $user]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(User $user): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => ['user' => $this->userManagementService->getUserWithCompleteData($user)]
    ]);
}

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->updateUser($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            $this->userManagementService->deleteUser($user);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->assignRolesToUser($user, $request->roles);

            return response()->json([
                'success' => true,
                'message' => 'Roles assigned successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign roles',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function removeRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->removeRolesFromUser($user, $request->roles);

            return response()->json([
                'success' => true,
                'message' => 'Roles removed successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove roles',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function syncRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->syncUserRoles($user, $request->roles);

            return response()->json([
                'success' => true,
                'message' => 'Roles synced successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync roles',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function givePermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->givePermissionsToUser($user, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions granted successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant permissions',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function revokePermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->revokePermissionsFromUser($user, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions revoked successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke permissions',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function syncPermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userManagementService->syncUserPermissions($user, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions synced successfully',
                'data' => ['user' => $updatedUser]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync permissions',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
