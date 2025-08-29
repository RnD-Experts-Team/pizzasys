<?php

namespace App\Services\V1\Roles;

use App\Models\Role;
use App\Models\Store;
use App\Models\RoleHierarchy;
use Illuminate\Support\Collection;

class RoleHierarchyService
{
    public function createHierarchy(array $data): RoleHierarchy
    {
        // Validate hierarchy before creation
        $errors = $this->validateHierarchy(
            $data['higher_role_id'],
            $data['lower_role_id'],
            $data['store_id']
        );

        if (!empty($errors)) {
            throw new \Exception('Invalid hierarchy: ' . implode(', ', $errors));
        }

        return RoleHierarchy::create([
            'higher_role_id' => $data['higher_role_id'],
            'lower_role_id' => $data['lower_role_id'],
            'store_id' => $data['store_id'],
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function removeHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId): bool
    {
        return RoleHierarchy::where('higher_role_id', $higherRoleId)
            ->where('lower_role_id', $lowerRoleId)
            ->where('store_id', $storeId)
            ->delete();
    }

    public function getStoreHierarchy(string $storeId)
    {
        return RoleHierarchy::where('store_id', $storeId)
            ->where('is_active', true)
            ->with(['higherRole', 'lowerRole'])
            ->get();
    }

    public function getRoleHierarchyTree(string $storeId): array
    {
        $hierarchies = $this->getStoreHierarchy($storeId);
        $tree = [];

        // Find root roles (roles that don't have higher roles)
        $allLowerRoles = $hierarchies->pluck('lower_role_id')->unique();
        $allHigherRoles = $hierarchies->pluck('higher_role_id')->unique();
        $rootRoles = $allHigherRoles->diff($allLowerRoles);

        foreach ($rootRoles as $rootRoleId) {
            $rootRole = Role::find($rootRoleId);
            if ($rootRole) {
                $processed = []; // Reset for each root
                $tree[] = $this->buildHierarchyTree($rootRole, $storeId, $hierarchies, $processed);
            }
        }

        return $tree;
    }

    private function buildHierarchyTree(Role $role, string $storeId, $hierarchies, array &$processed): array
    {
        if (in_array($role->id, $processed)) {
            return ['role' => $role, 'children' => [], 'circular_detected' => true];
        }

        $processed[] = $role->id;
        $children = [];

        $childHierarchies = $hierarchies->where('higher_role_id', $role->id);
        foreach ($childHierarchies as $hierarchy) {
            $childRole = $hierarchy->lowerRole;
            if ($childRole) {
                $children[] = $this->buildHierarchyTree($childRole, $storeId, $hierarchies, $processed);
            }
        }

        // Remove role from processed after processing its children
        array_pop($processed);

        return [
            'role' => $role,
            'children' => $children,
            'permissions' => $role->permissions ?? []
        ];
    }

    public function validateHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId): array
    {
        $errors = [];

        // Check if roles exist
        if (!Role::find($higherRoleId)) {
            $errors[] = 'Higher role does not exist';
        }
        if (!Role::find($lowerRoleId)) {
            $errors[] = 'Lower role does not exist';
        }

        // Check if store exists
        if (!Store::find($storeId)) {
            $errors[] = 'Store does not exist';
        }

        // Check for self-reference
        if ($higherRoleId === $lowerRoleId) {
            $errors[] = 'A role cannot manage itself';
        }

        // Check for existing hierarchy
        $existing = RoleHierarchy::where('higher_role_id', $higherRoleId)
            ->where('lower_role_id', $lowerRoleId)
            ->where('store_id', $storeId)
            ->exists();

        if ($existing) {
            $errors[] = 'This hierarchy relationship already exists';
        }

        // Check for circular hierarchy using comprehensive detection
        if ($this->wouldCreateCircularHierarchy($higherRoleId, $lowerRoleId, $storeId)) {
            $errors[] = 'This would create a circular hierarchy';
        }

        return $errors;
    }

    /**
     * Comprehensive circular hierarchy detection using graph traversal
     */
    private function wouldCreateCircularHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId): bool
    {
        // Get all existing hierarchies for the store
        $hierarchies = RoleHierarchy::where('store_id', $storeId)
            ->where('is_active', true)
            ->get(['higher_role_id', 'lower_role_id']);

        // Create a temporary hierarchy to test
        $testHierarchy = collect([
            ['higher_role_id' => $higherRoleId, 'lower_role_id' => $lowerRoleId]
        ]);

        // Combine existing and test hierarchy
        $allHierarchies = $hierarchies->concat($testHierarchy);

        // Build adjacency list (role_id => [subordinate_role_ids])
        $adjacencyList = [];
        foreach ($allHierarchies as $hierarchy) {
            $higher = $hierarchy['higher_role_id'];
            $lower = $hierarchy['lower_role_id'];
            
            if (!isset($adjacencyList[$higher])) {
                $adjacencyList[$higher] = [];
            }
            $adjacencyList[$higher][] = $lower;
        }

        // Check for cycles using DFS from each node
        return $this->hasCycleInGraph($adjacencyList);
    }

    /**
     * Detect cycles in directed graph using DFS
     */
    private function hasCycleInGraph(array $adjacencyList): bool
    {
        $visited = [];
        $recursionStack = [];

        foreach ($adjacencyList as $node => $neighbors) {
            if (!isset($visited[$node])) {
                if ($this->hasCycleDFS($node, $adjacencyList, $visited, $recursionStack)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * DFS helper to detect cycles
     */
    private function hasCycleDFS(int $node, array $adjacencyList, array &$visited, array &$recursionStack): bool
    {
        $visited[$node] = true;
        $recursionStack[$node] = true;

        if (isset($adjacencyList[$node])) {
            foreach ($adjacencyList[$node] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    if ($this->hasCycleDFS($neighbor, $adjacencyList, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$neighbor]) && $recursionStack[$neighbor]) {
                    return true; // Cycle detected
                }
            }
        }

        $recursionStack[$node] = false;
        return false;
    }

    /**
     * Get all roles that would be affected if this hierarchy is created
     */
    public function getAffectedRoles(int $higherRoleId, int $lowerRoleId, string $storeId): array
    {
        $hierarchies = RoleHierarchy::where('store_id', $storeId)
            ->where('is_active', true)
            ->get();

        $affected = collect([$higherRoleId, $lowerRoleId]);

        // Add all subordinates of the lower role
        $subordinates = $this->getAllSubordinates($lowerRoleId, $hierarchies);
        $affected = $affected->merge($subordinates);

        // Add all superiors of the higher role
        $superiors = $this->getAllSuperiors($higherRoleId, $hierarchies);
        $affected = $affected->merge($superiors);

        return $affected->unique()->values()->toArray();
    }

    private function getAllSubordinates(int $roleId, Collection $hierarchies): Collection
    {
        $subordinates = collect();
        $directSubordinates = $hierarchies->where('higher_role_id', $roleId)->pluck('lower_role_id');

        foreach ($directSubordinates as $subordinate) {
            $subordinates->push($subordinate);
            $subordinates = $subordinates->merge($this->getAllSubordinates($subordinate, $hierarchies));
        }

        return $subordinates;
    }

    private function getAllSuperiors(int $roleId, Collection $hierarchies): Collection
    {
        $superiors = collect();
        $directSuperiors = $hierarchies->where('lower_role_id', $roleId)->pluck('higher_role_id');

        foreach ($directSuperiors as $superior) {
            $superiors->push($superior);
            $superiors = $superiors->merge($this->getAllSuperiors($superior, $hierarchies));
        }

        return $superiors;
    }
}
