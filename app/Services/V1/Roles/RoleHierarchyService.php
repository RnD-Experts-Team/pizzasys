<?php

namespace App\Services\V1\Roles;

use App\Models\Role;
use App\Models\Store;
use App\Models\RoleHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Jobs\PublishAuthOutboxEventJob;

class RoleHierarchyService
{
    /**
     * Record event to outbox and dispatch publish job AFTER COMMIT.
     */
    private function recordEvent(string $subject, array $data, ?Request $request = null): void
    {
        $factory = app(AuthEventFactory::class);
        $outbox  = app(AuthOutboxService::class);

        $envelope = $factory->make($subject, $data, $request);
        $row = $outbox->record($subject, $envelope);

        DB::afterCommit(fn() => PublishAuthOutboxEventJob::dispatch($row->id));
    }

    /**
     * Event: auth.v1.assignment.role_hierarchy.created
     */
    public function createHierarchy(array $data, ?Request $request = null): RoleHierarchy
    {
        return DB::transaction(function () use ($data, $request) {

            // Validate hierarchy before creation
            $errors = $this->validateHierarchy(
                $data['higher_role_id'],
                $data['lower_role_id'],
                $data['store_id']
            );

            if (!empty($errors)) {
                throw new \Exception('Invalid hierarchy: ' . implode(', ', $errors));
            }

            $hierarchy = RoleHierarchy::create([
                'higher_role_id' => $data['higher_role_id'],
                'lower_role_id' => $data['lower_role_id'],
                'store_id' => $data['store_id'],
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->recordEvent('auth.v1.assignment.role_hierarchy.created', [
                'hierarchy' => [
                    'id' => $hierarchy->id,
                    'store_id' => $hierarchy->store_id,
                    'higher_role_id' => (int) $hierarchy->higher_role_id,
                    'lower_role_id' => (int) $hierarchy->lower_role_id,
                    'is_active' => (bool) $hierarchy->is_active,
                    'metadata' => $hierarchy->metadata,
                    'created_at' => optional($hierarchy->created_at)?->toIso8601String(),
                    'updated_at' => optional($hierarchy->updated_at)?->toIso8601String(),
                ],
            ], $request);

            return $hierarchy;
        });
    }

    /**
     * Event: auth.v1.assignment.role_hierarchy.removed
     *
     * Note: we only publish if a row was actually deleted.
     */
    public function removeHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($higherRoleId, $lowerRoleId, $storeId, $request) {

            // optional: capture hierarchy id before delete (for traceability)
            $row = RoleHierarchy::where('higher_role_id', $higherRoleId)
                ->where('lower_role_id', $lowerRoleId)
                ->where('store_id', $storeId)
                ->first();

            $deleted = RoleHierarchy::where('higher_role_id', $higherRoleId)
                ->where('lower_role_id', $lowerRoleId)
                ->where('store_id', $storeId)
                ->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.assignment.role_hierarchy.removed', [
                    'store_id' => $storeId,
                    'higher_role_id' => $higherRoleId,
                    'lower_role_id' => $lowerRoleId,
                    'hierarchy_id' => $row?->id,
                    'removed_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return (bool) $deleted;
        });
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

        if (!Role::find($higherRoleId)) {
            $errors[] = 'Higher role does not exist';
        }
        if (!Role::find($lowerRoleId)) {
            $errors[] = 'Lower role does not exist';
        }

        if (!Store::find($storeId)) {
            $errors[] = 'Store does not exist';
        }

        if ($higherRoleId === $lowerRoleId) {
            $errors[] = 'A role cannot manage itself';
        }

        $existing = RoleHierarchy::where('higher_role_id', $higherRoleId)
            ->where('lower_role_id', $lowerRoleId)
            ->where('store_id', $storeId)
            ->exists();

        if ($existing) {
            $errors[] = 'This hierarchy relationship already exists';
        }

        if ($this->wouldCreateCircularHierarchy($higherRoleId, $lowerRoleId, $storeId)) {
            $errors[] = 'This would create a circular hierarchy';
        }

        return $errors;
    }

    private function wouldCreateCircularHierarchy(int $higherRoleId, int $lowerRoleId, string $storeId): bool
    {
        $hierarchies = RoleHierarchy::where('store_id', $storeId)
            ->where('is_active', true)
            ->get(['higher_role_id', 'lower_role_id']);

        $testHierarchy = collect([
            ['higher_role_id' => $higherRoleId, 'lower_role_id' => $lowerRoleId]
        ]);

        $allHierarchies = $hierarchies->concat($testHierarchy);

        $adjacencyList = [];
        foreach ($allHierarchies as $hierarchy) {
            $higher = $hierarchy['higher_role_id'];
            $lower = $hierarchy['lower_role_id'];

            if (!isset($adjacencyList[$higher])) {
                $adjacencyList[$higher] = [];
            }
            $adjacencyList[$higher][] = $lower;
        }

        return $this->hasCycleInGraph($adjacencyList);
    }

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
                    return true;
                }
            }
        }

        $recursionStack[$node] = false;
        return false;
    }

    public function getAffectedRoles(int $higherRoleId, int $lowerRoleId, string $storeId): array
    {
        $hierarchies = RoleHierarchy::where('store_id', $storeId)
            ->where('is_active', true)
            ->get();

        $affected = collect([$higherRoleId, $lowerRoleId]);

        $subordinates = $this->getAllSubordinates($lowerRoleId, $hierarchies);
        $affected = $affected->merge($subordinates);

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
