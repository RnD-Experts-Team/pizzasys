<?php

namespace App\Services\V1\ServiceClients;

use App\Models\ServiceClient;
use Carbon\Carbon;

class ServiceClientManagementService
{
    public function getAllServices($perPage = 15, $search = null)
    {
        $query = ServiceClient::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function createService(array $data): array
    {
        $tokenData = ServiceClient::generateToken();
        
        $expiresAt = null;
        if (isset($data['expires_at']) && $data['expires_at']) {
            $expiresAt = Carbon::parse($data['expires_at'])->endOfDay();
        }

        $service = ServiceClient::create([
            'name' => $data['name'],
            'token_hash' => $tokenData['hash'],
            'is_active' => $data['is_active'] ?? true,
            'expires_at' => $expiresAt,
            'notes' => $data['notes'] ?? null,
        ]);

        return [
            'service' => $service,
            'token' => $tokenData['plain'] // Only returned once!
        ];
    }

    public function updateService(ServiceClient $service, array $data): ServiceClient
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }
        
        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = $data['expires_at'] 
                ? Carbon::parse($data['expires_at'])->endOfDay() 
                : null;
        }

        $service->update($updateData);
        return $service->fresh();
    }

    public function rotateServiceToken(ServiceClient $service, array $data = []): array
    {
        $tokenData = ServiceClient::generateToken();
        
        $updateData = ['token_hash' => $tokenData['hash']];
        
        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = $data['expires_at'] 
                ? Carbon::parse($data['expires_at'])->endOfDay() 
                : null;
        }

        $service->update($updateData);

        return [
            'service' => $service->fresh(),
            'token' => $tokenData['plain'] // Only returned once!
        ];
    }

    public function deleteService(ServiceClient $service): bool
    {
        return $service->delete();
    }

    public function toggleServiceStatus(ServiceClient $service): ServiceClient
    {
        $service->update(['is_active' => !$service->is_active]);
        return $service->fresh();
    }
}
