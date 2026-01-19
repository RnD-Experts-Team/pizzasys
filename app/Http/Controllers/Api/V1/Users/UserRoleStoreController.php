<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\AssignUserRoleStoreRequest;
use App\Services\V1\Users\UserRoleStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleStoreController extends Controller
{
    public function __construct(
        private UserRoleStoreService $userRoleStoreService
    ) {}

    public function assign(AssignUserRoleStoreRequest $request): JsonResponse
    {
        try {
            $assignment = $this->userRoleStoreService->assignUserRoleStore($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'User role store assigned successfully',
                'data' => ['assignment' => $assignment->load(['user', 'role', 'store'])]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'store_id' => 'required|string|exists:stores,id',
        ]);

        try {
            $removed = $this->userRoleStoreService->removeUserRoleStore(
                $request->user_id,
                $request->role_id,
                $request->store_id,
                $request
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => 'User role store removed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'store_id' => 'required|string|exists:stores,id',
        ]);

        try {
            $toggled = $this->userRoleStoreService->toggleUserRoleStore(
                $request->user_id,
                $request->role_id,
                $request->store_id,
                $request
            );

            if ($toggled) {
                return response()->json([
                    'success' => true,
                    'message' => 'User role store status toggled successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle user role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getUserAssignments(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'store_id' => 'sometimes|string|exists:stores,id',
        ]);

        $assignments = $this->userRoleStoreService->getUserRoleStoreAssignments(
            $request->user_id,
            $request->get('store_id')
        );

        return response()->json([
            'success' => true,
            'data' => ['assignments' => $assignments]
        ]);
    }

    public function getStoreAssignments(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|string|exists:stores,id',
            'role_id' => 'sometimes|integer|exists:roles,id',
        ]);

        $assignments = $this->userRoleStoreService->getStoreRoleAssignments(
            $request->store_id,
            $request->get('role_id')
        );

        return response()->json([
            'success' => true,
            'data' => ['assignments' => $assignments]
        ]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.role_id' => 'required|integer|exists:roles,id',
            'assignments.*.store_id' => 'required|string|exists:stores,id',
            'assignments.*.metadata' => 'sometimes|array',
            'assignments.*.is_active' => 'sometimes|boolean',
        ]);

        try {
            $results = $this->userRoleStoreService->bulkAssignUserRoleStore(
                $request->user_id,
                $request->assignments,
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignments completed successfully',
                'data' => ['assignments' => $results]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete bulk assignments',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
