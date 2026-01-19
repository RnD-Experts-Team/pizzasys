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
            'store_id' => 'required|string|exists:stores,id',
        ]);

        try {
            $removed = $this->hierarchyService->removeHierarchy(
                $request->higher_role_id,
                $request->lower_role_id,
                $request->store_id,
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
            'store_id' => 'required|string|exists:stores,id',
        ]);

        $hierarchies = $this->hierarchyService->getStoreHierarchy($request->store_id);

        return response()->json([
            'success' => true,
            'data' => ['hierarchies' => $hierarchies]
        ]);
    }

    public function getHierarchyTree(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|string|exists:stores,id',
        ]);

        $tree = $this->hierarchyService->getRoleHierarchyTree($request->store_id);

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
            'store_id' => 'required|string',
        ]);

        $errors = $this->hierarchyService->validateHierarchy(
            $request->higher_role_id,
            $request->lower_role_id,
            $request->store_id
        );

        return response()->json([
            'success' => !empty($errors),
            'valid' => !empty($errors),
            'errors' => $errors
        ]);
    }
}
