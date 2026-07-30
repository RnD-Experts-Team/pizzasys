<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\EmployeeLoginRequest;
use App\Services\V1\Auth\EmployeeAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAuthController extends Controller
{
    public function __construct(
        private EmployeeAuthService $employeeAuthService
    ) {}

    public function login(EmployeeLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->employeeAuthService->login(
                (int) $request->employee_id,
                $request->password,
                $request->input('device'),
                $request->input('fcm_token'),
                $request->input('client_type', 'web'),
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $employee = $request->user();
        $employeeData = $this->employeeAuthService->getEmployeeCompleteData($employee);

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employeeData
            ]
        ]);
    }
}
