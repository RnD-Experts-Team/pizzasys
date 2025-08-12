<?php

namespace App\Http\Controllers\Api\V1\ServiceClients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ServiceClients\CreateServiceClientRequest;
use App\Http\Requests\Api\V1\ServiceClients\UpdateServiceClientRequest;
use App\Http\Requests\Api\V1\ServiceClients\RotateServiceTokenRequest;
use App\Services\V1\ServiceClients\ServiceClientManagementService;
use App\Models\ServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceClientController extends Controller
{
    public function __construct(
        private ServiceClientManagementService $serviceClientService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $services = $this->serviceClientService->getAllServices(
            $request->get('per_page', 15),
            $request->get('search')
        );

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    public function store(CreateServiceClientRequest $request): JsonResponse
    {
        try {
            $result = $this->serviceClientService->createService($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Service client created successfully',
                'data' => [
                    'service' => $result['service'],
                    'token' => $result['token'] // Only shown once!
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service client',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(ServiceClient $serviceClient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['service' => $serviceClient]
        ]);
    }

    public function update(UpdateServiceClientRequest $request, ServiceClient $serviceClient): JsonResponse
    {
        try {
            $updatedService = $this->serviceClientService->updateService($serviceClient, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Service client updated successfully',
                'data' => ['service' => $updatedService]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service client',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(ServiceClient $serviceClient): JsonResponse
    {
        try {
            $this->serviceClientService->deleteService($serviceClient);

            return response()->json([
                'success' => true,
                'message' => 'Service client deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service client',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function rotateToken(RotateServiceTokenRequest $request, ServiceClient $serviceClient): JsonResponse
{
    try {
        $result = $this->serviceClientService->rotateServiceToken(
            $serviceClient, 
            $request->getProcessedData() // Use the processed data method
        );

        return response()->json([
            'success' => true,
            'message' => 'Service token rotated successfully',
            'data' => [
                'service' => $result['service'],
                'token' => $result['token'] // Only shown once!
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to rotate service token',
            'error' => $e->getMessage()
        ], 400);
    }
}

    public function toggleStatus(ServiceClient $serviceClient): JsonResponse
    {
        try {
            $updatedService = $this->serviceClientService->toggleServiceStatus($serviceClient);

            return response()->json([
                'success' => true,
                'message' => 'Service status toggled successfully',
                'data' => ['service' => $updatedService]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle service status',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
