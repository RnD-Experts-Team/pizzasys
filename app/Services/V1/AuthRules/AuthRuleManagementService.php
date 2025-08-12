<?php

namespace App\Services\V1\AuthRules;

use App\Models\AuthRule;

class AuthRuleManagementService
{
    public function getAllRules($perPage = 15, $search = null, $service = null)
    {
        $query = AuthRule::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('service', 'like', "%{$search}%")
                  ->orWhere('path_dsl', 'like', "%{$search}%")
                  ->orWhere('route_name', 'like', "%{$search}%");
            });
        }

        if ($service) {
            $query->where('service', $service);
        }

        return $query->orderBy('service')
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->paginate($perPage);
    }

    public function createRule(array $data): AuthRule
    {
        $methods = is_array($data['method']) ? $data['method'] : [$data['method']];
        $methods = array_map('strtoupper', $methods);

        // Determine if this is a route or path rule
        $isRoute = isset($data['route_name']) && $data['route_name'];
        $routeName = $isRoute ? $data['route_name'] : null;
        $pathDsl = $isRoute ? null : ($data['path_dsl'] ?? null);
        $pathRegex = $pathDsl ? AuthRule::compilePathDsl($pathDsl) : null;

        // For now, create one rule per method
        $rule = null;
        foreach ($methods as $method) {
            $rule = AuthRule::create([
                'service' => $data['service'],
                'method' => $method,
                'route_name' => $routeName,
                'path_dsl' => $pathDsl,
                'path_regex' => $pathRegex,
                'roles_any' => !empty($data['roles_any']) ? array_values($data['roles_any']) : null,
                'permissions_any' => !empty($data['permissions_any']) ? array_values($data['permissions_any']) : null,
                'permissions_all' => !empty($data['permissions_all']) ? array_values($data['permissions_all']) : null,
                'priority' => $data['priority'] ?? 100,
                'is_active' => $data['is_active'] ?? true,
            ]);
        }

        return $rule; // Return the last created rule
    }

    public function updateRule(AuthRule $rule, array $data): AuthRule
    {
        $updateData = [];
        
        foreach (['service', 'method', 'route_name', 'path_dsl', 'priority', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (isset($data['method'])) {
            $updateData['method'] = strtoupper($data['method']);
        }

        // Recompile regex if path_dsl changed
        if (isset($data['path_dsl'])) {
            $updateData['path_regex'] = AuthRule::compilePathDsl($data['path_dsl']);
        }

        foreach (['roles_any', 'permissions_any', 'permissions_all'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = !empty($data[$field]) ? array_values($data[$field]) : null;
            }
        }

        $rule->update($updateData);
        return $rule->fresh();
    }

    public function deleteRule(AuthRule $rule): bool
    {
        return $rule->delete();
    }

    public function toggleRuleStatus(AuthRule $rule): AuthRule
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return $rule->fresh();
    }

    public function getServicesList(): array
    {
        return AuthRule::distinct('service')->pluck('service')->toArray();
    }

    public function testRule(array $data): array
    {
        // Test path compilation
        $pathDsl = $data['path_dsl'] ?? null;
        $pathRegex = $pathDsl ? AuthRule::compilePathDsl($pathDsl) : null;
        
        $testPath = $data['test_path'] ?? '/';
        $matches = false;
        
        if ($pathRegex) {
            $matches = @preg_match($pathRegex, $testPath) === 1;
        }

        return [
            'path_dsl' => $pathDsl,
            'path_regex' => $pathRegex,
            'test_path' => $testPath,
            'matches' => $matches,
            'compiled_successfully' => $pathRegex !== null
        ];
    }
}
