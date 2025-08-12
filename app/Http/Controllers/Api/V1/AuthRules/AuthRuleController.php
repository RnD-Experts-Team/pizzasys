<?php

namespace App\Http\Controllers\Api\V1\AuthRules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthRules\CreateAuthRuleRequest;
use App\Http\Requests\Api\V1\AuthRules\UpdateAuthRuleRequest;
use App\Services\V1\AuthRules\AuthRuleManagementService;
use App\Models\AuthRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthRuleController extends Controller
{
    public function __construct(
        private AuthRuleManagementService $authRuleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rules = $this->authRuleService->getAllRules(
            $request->get('per_page', 15),
            $request->get('search'),
            $request->get('service')
        );

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    public function store(CreateAuthRuleRequest $request): JsonResponse
    {
        try {
            $rule = $this->authRuleService->createRule($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Authorization rule created successfully',
                'data' => ['rule' => $rule]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create authorization rule',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(AuthRule $authRule): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['rule' => $authRule]
        ]);
    }

    public function update(UpdateAuthRuleRequest $request, AuthRule $authRule): JsonResponse
    {
        try {
            $updatedRule = $this->authRuleService->updateRule($authRule, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Authorization rule updated successfully',
                'data' => ['rule' => $updatedRule]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update authorization rule',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(AuthRule $authRule): JsonResponse
    {
        try {
            $this->authRuleService->deleteRule($authRule);

            return response()->json([
                'success' => true,
                'message' => 'Authorization rule deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete authorization rule',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function toggleStatus(AuthRule $authRule): JsonResponse
    {
        try {
            $updatedRule = $this->authRuleService->toggleRuleStatus($authRule);

            return response()->json([
                'success' => true,
                'message' => 'Authorization rule status toggled successfully',
                'data' => ['rule' => $updatedRule]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle rule status',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getServices(): JsonResponse
    {
        $services = $this->authRuleService->getServicesList();

        return response()->json([
            'success' => true,
            'data' => ['services' => $services]
        ]);
    }

    public function testRule(Request $request): JsonResponse
    {
        $request->validate([
            'path_dsl' => 'required|string',
            'test_path' => 'required|string'
        ]);

        $result = $this->authRuleService->testRule($request->only(['path_dsl', 'test_path']));

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
