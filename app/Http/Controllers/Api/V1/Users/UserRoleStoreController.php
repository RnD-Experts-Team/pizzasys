<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\AssignUserRoleStoreRequest;
use App\Services\V1\Users\UserRoleStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        try {
            $removed = $this->userRoleStoreService->removeUserRoleStore(
                (int) $request->user_id,
                (int) $request->role_id,
                (int) $request->store_id,
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
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        try {
            $toggled = $this->userRoleStoreService->toggleUserRoleStore(
                (int) $request->user_id,
                (int) $request->role_id,
                (int) $request->store_id,
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

    /**
     * Several assign / toggle / remove ops for one user, all or nothing. Answers
     * with the user's resulting assignments, inactive ones included.
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'ops' => 'required|array|min:1',
            'ops.*.op' => 'required|string|in:assign,toggle,remove',
            'ops.*.role_id' => 'required|integer|exists:roles,id',
            'ops.*.store_id' => 'required|integer|exists:stores,id',
            'ops.*.metadata' => 'sometimes|nullable|array|prohibited_unless:ops.*.op,assign',
            'ops.*.is_active' => 'sometimes|boolean|prohibited_if:ops.*.op,remove',
        ]);

        try {
            $assignments = $this->userRoleStoreService->applyUserRoleStoreOps(
                (int) $request->user_id,
                $request->ops,
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Store access applied successfully',
                'data' => ['assignments' => $assignments]
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply store access',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getUserAssignments(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'store_id' => 'sometimes|integer|exists:stores,id',
        ]);

        $assignments = $this->userRoleStoreService->getUserRoleStoreAssignments(
            (int) $request->user_id,
            $request->filled('store_id') ? (int) $request->get('store_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => ['assignments' => $assignments]
        ]);
    }

    public function getStoreAssignments(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'role_id' => 'sometimes|integer|exists:roles,id',
        ]);

        $assignments = $this->userRoleStoreService->getStoreRoleAssignments(
            (int) $request->store_id,
            $request->filled('role_id') ? (int) $request->get('role_id') : null
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
            'assignments.*.store_id' => 'required|integer|exists:stores,id',
            'assignments.*.metadata' => 'sometimes|array',
            'assignments.*.is_active' => 'sometimes|boolean',
        ]);

        try {
            $results = $this->userRoleStoreService->bulkAssignUserRoleStore(
                (int) $request->user_id,
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
