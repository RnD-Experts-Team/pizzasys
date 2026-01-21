<?php

namespace App\Http\Controllers\Api\V1\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Roles\CreateRoleHierarchyRequest;
use App\Services\V1\Roles\RoleHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleHierarchyController extends Controller
{
    public function __construct(
        private RoleHierarchyService $hierarchyService
    ) {}

    public function store(CreateRoleHierarchyRequest $request): JsonResponse
    {
        try {
            $hierarchy = $this->hierarchyService->createHierarchy($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Role hierarchy created successfully',
                'data' => ['hierarchy' => $hierarchy->load(['higherRole', 'lowerRole', 'store'])]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role hierarchy',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'higher_role_id' => 'required|integer|exists:roles,id',
            'lower_role_id' => 'required|integer|exists:roles,id',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        try {
            $removed = $this->hierarchyService->removeHierarchy(
                (int) $request->higher_role_id,
                (int) $request->lower_role_id,
                (int) $request->store_id,
                $request
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role hierarchy removed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Hierarchy not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role hierarchy',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getStoreHierarchy(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        $hierarchies = $this->hierarchyService->getStoreHierarchy((int) $request->store_id);

        return response()->json([
            'success' => true,
            'data' => ['hierarchies' => $hierarchies]
        ]);
    }

    public function getHierarchyTree(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        $tree = $this->hierarchyService->getRoleHierarchyTree((int) $request->store_id);

        return response()->json([
            'success' => true,
            'data' => ['hierarchy_tree' => $tree]
        ]);
    }

    public function validateHierarchy(Request $request): JsonResponse
    {
        $request->validate([
            'higher_role_id' => 'required|integer',
            'lower_role_id' => 'required|integer',
            'store_id' => 'required|integer',
        ]);

        $errors = $this->hierarchyService->validateHierarchy(
            (int) $request->higher_role_id,
            (int) $request->lower_role_id,
            (int) $request->store_id
        );

        $isValid = empty($errors);

        return response()->json([
            'success' => $isValid,
            'valid' => $isValid,
            'errors' => $errors
        ]);
    }
}
