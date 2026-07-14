<?php

namespace App\Http\Controllers\Api\V1\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Employees\AssignEmployeeRoleStoreRequest;
use App\Services\V1\Employees\EmployeeRoleStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeRoleStoreController extends Controller
{
    public function __construct(
        private EmployeeRoleStoreService $employeeRoleStoreService
    ) {}

    public function assign(AssignEmployeeRoleStoreRequest $request): JsonResponse
    {
        try {
            $assignment = $this->employeeRoleStoreService->assignEmployeeRoleStore($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Employee role store assigned successfully',
                'data' => ['assignment' => $assignment->load(['employee', 'role', 'store'])]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign employee role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'role_id' => 'required|integer|exists:roles,id',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        try {
            $removed = $this->employeeRoleStoreService->removeEmployeeRoleStore(
                (int) $request->employee_id,
                (int) $request->role_id,
                (int) $request->store_id,
                $request
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee role store removed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove employee role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'role_id' => 'required|integer|exists:roles,id',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        try {
            $toggled = $this->employeeRoleStoreService->toggleEmployeeRoleStore(
                (int) $request->employee_id,
                (int) $request->role_id,
                (int) $request->store_id,
                $request
            );

            if ($toggled) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee role store status toggled successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle employee role store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getEmployeeAssignments(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_id' => 'sometimes|integer|exists:stores,id',
        ]);

        $assignments = $this->employeeRoleStoreService->getEmployeeRoleStoreAssignments(
            (int) $request->employee_id,
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

        $assignments = $this->employeeRoleStoreService->getStoreRoleAssignments(
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
            'employee_id' => 'required|integer|exists:employees,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.role_id' => 'required|integer|exists:roles,id',
            'assignments.*.store_id' => 'required|integer|exists:stores,id',
            'assignments.*.metadata' => 'sometimes|array',
            'assignments.*.is_active' => 'sometimes|boolean',
        ]);

        try {
            $results = $this->employeeRoleStoreService->bulkAssignEmployeeRoleStore(
                (int) $request->employee_id,
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
