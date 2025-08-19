<?php

namespace App\Services\V1\Roles;

use App\Models\Role;
use App\Models\Store;
use App\Models\RoleHierarchy;

class RoleHierarchyService
{
    public function createHierarchy(array $data): RoleHierarchy
    {
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
        $processed = [];

        // Find root roles (roles that don't have higher roles)
        $allLowerRoles = $hierarchies->pluck('lower_role_id')->unique();
        $allHigherRoles = $hierarchies->pluck('higher_role_id')->unique();
        $rootRoles = $allHigherRoles->diff($allLowerRoles);

        foreach ($rootRoles as $rootRoleId) {
            $rootRole = Role::find($rootRoleId);
            if ($rootRole) {
                $tree[] = $this->buildHierarchyTree($rootRole, $storeId, $hierarchies, $processed);
            }
        }

        return $tree;
    }

    private function buildHierarchyTree(Role $role, string $storeId, $hierarchies, array &$processed): array
    {
        if (in_array($role->id, $processed)) {
            return ['role' => $role, 'children' => []]; // Prevent infinite loops
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

        return [
            'role' => $role,
            'children' => $children,
            'permissions' => $role->permissions
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

        // Check for circular hierarchy
        $wouldCreateCircle = $this->wouldCreateCircularHierarchy($higherRoleId, $lowerRoleId, $storeId);
        if ($wouldCreateCircle) {
            $errors[] = 'This would create a circular hierarchy';
        }

        return $errors;
    }

    private function wouldCreateCircularHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId): bool
    {
        // Check if lowerRole is already higher than higherRole
        $lowerRole = Role::find($lowerRoleId);
        $higherRole = Role::find($higherRoleId);

        if (!$lowerRole || !$higherRole) {
            return false;
        }

        return $lowerRole->isHigherThan($higherRole, $storeId);
    }
}
