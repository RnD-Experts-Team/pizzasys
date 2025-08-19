<?php

namespace App\Http\Controllers\Api\V1\Stores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stores\CreateStoreRequest;
use App\Http\Requests\Api\V1\Stores\UpdateStoreRequest;
use App\Services\V1\Stores\StoreManagementService;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private StoreManagementService $storeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stores = $this->storeService->getAllStores(
            $request->get('per_page', 15),
            $request->get('search')
        );

        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }

    public function store(CreateStoreRequest $request): JsonResponse
    {
        try {
            $store = $this->storeService->createStore($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Store created successfully',
                'data' => ['store' => $store]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Store $store): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['store' => $store]
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        try {
            $updatedStore = $this->storeService->updateStore($store, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Store updated successfully',
                'data' => ['store' => $updatedStore]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Store $store): JsonResponse
    {
        try {
            $this->storeService->deleteStore($store);

            return response()->json([
                'success' => true,
                'message' => 'Store deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete store',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getUsers(Store $store, Request $request): JsonResponse
    {
        $users = $this->storeService->getStoreUsers($store, $request->get('role_id'));

        return response()->json([
            'success' => true,
            'data' => ['users' => $users]
        ]);
    }

    public function getRoles(Store $store, Request $request): JsonResponse
    {
        $roles = $this->storeService->getStoreRoles($store, $request->get('user_id'));

        return response()->json([
            'success' => true,
            'data' => ['roles' => $roles]
        ]);
    }
}
