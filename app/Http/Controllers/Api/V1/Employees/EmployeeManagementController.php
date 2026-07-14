<?php

namespace App\Http\Controllers\Api\V1\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Employees\UpdateEmployeePasswordRequest;
use App\Models\Employee;
use App\Services\V1\Employees\EmployeeManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeManagementController extends Controller
{
    public function __construct(
        private EmployeeManagementService $employeeManagementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|nullable|string|max:100',
            'store_id' => 'sometimes|nullable|string|max:50',
            'active' => 'sometimes|boolean',
        ]);

        $employees = $this->employeeManagementService->getAllEmployees(
            (int) $request->input('per_page', 15),
            $request->input('search'),
            $request->input('store_id'),
            $request->has('active') ? $request->boolean('active') : null
        );

        return response()->json([
            'success' => true,
            'data' => ['employees' => $employees]
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['employee' => $this->employeeManagementService->getEmployeeDetails($employee)]
        ]);
    }

    public function updatePassword(UpdateEmployeePasswordRequest $request, Employee $employee): JsonResponse
    {
        try {
            $this->employeeManagementService->updatePassword($employee, $request->password, $request);

            return response()->json([
                'success' => true,
                'message' => 'Employee password updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee password',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function activate(Request $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeManagementService->activate($employee, $request);

        return response()->json([
            'success' => true,
            'message' => 'Employee activated successfully',
            'data' => ['employee' => $employee]
        ]);
    }

    public function deactivate(Request $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeManagementService->deactivate($employee, $request);

        return response()->json([
            'success' => true,
            'message' => 'Employee deactivated successfully',
            'data' => ['employee' => $employee]
        ]);
    }
}
