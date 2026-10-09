<?php

namespace Database\Seeders\AuthRules;

use App\Models\AuthRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Base for the per-service auth rule seeders. Each child is the full,
 * reviewable list of one service's rules: one row per route.
 *
 * Run everything with:
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 *   php artisan db:seed --class=AuthRulesSeeder
 * or one service with --class="Database\Seeders\AuthRules\MaintenanceAuthRulesSeeder".
 *
 * How AuthorizationResolver matches (easy to get wrong):
 *  - the path is the service path WITHOUT the /api prefix;
 *  - `method` is compared exactly, so there is one row per verb (ANY never matches);
 *  - rules are ordered by priority ASC and the first path match wins;
 *  - `none` checks GLOBAL permissions only, `scoped` checks only the permissions
 *    the user holds through a store role FOR the store(s) in the request.
 *
 * Row format:
 *   [METHOD, path_dsl, scope, permissions?, 'allows_empty' => bool?, 'priority' => int?, 'roles' => [...]?]
 *
 * Rule shapes we use:
 *  | Situation                                         | Row                                               |
 *  |---------------------------------------------------|---------------------------------------------------|
 *  | Global catalog / HQ action                        | 'none' + global permission                        |
 *  | Store in the URL                                  | 'scoped' + permission held via the store role     |
 *  | Store-less path used by store staff AND by HQ     | 'scoped' + 'allows_empty' => true: a store in the |
 *  | (lists, analytics, multi-store reports)           | query/body/X-Store-Id header gets the per-store   |
 *  |                                                   | check, no store falls back to the global check    |
 *  | Self-service or harmless reference data           | 'none' with no permissions = any signed-in user   |
 *
 * Open rules are still written down: allow_if_no_rule=true would let them
 * through anyway, but the dashboard's canAccessRoute denies when no rule
 * matches, and the list doubles as the endpoint inventory.
 *
 * Seeding is additive: rows are upserted on (service, method, path_dsl) and
 * never deleted. Duplicate rows for one key are deactivated, and rules for
 * routes that no longer exist are listed in retired() and deactivated.
 */
abstract class AuthRuleSeeder extends Seeder
{
    /** The `service` string the service sends (its AUTH_SERVER_SERVICE_NAME). */
    abstract protected function service(): string;

    /** @return array<int, array> */
    abstract protected function rules(): array;

    /**
     * Rules for routes that no longer exist: [METHOD, path_dsl].
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function retired(): array
    {
        return [];
    }

    public function run(): void
    {
        $service = $this->service();
        $created = $updated = $deactivated = 0;

        foreach ($this->rules() as $row) {
            [$method, $path, $scope] = $row;
            $permissions = $row[3] ?? [];

            $existing = AuthRule::where('service', $service)
                ->where('method', $method)
                ->where('path_dsl', $path)
                ->orderBy('id')
                ->get();

            $rule = $existing->first() ?? new AuthRule(['service' => $service, 'method' => $method, 'path_dsl' => $path]);
            $rule->fill([
                'route_name' => null,
                'roles_any' => $row['roles'] ?? null,
                'permissions_any' => $permissions ?: null,
                'permissions_all' => null,
                'store_scope_mode' => $scope,
                'store_id_sources' => null,
                'store_match_policy' => 'all',
                'store_allows_empty' => (bool) ($row['allows_empty'] ?? false),
                'employee_accessible' => false,
                'is_active' => true,
                'priority' => $row['priority'] ?? (str_contains($path, '**') ? 10 : 1),
            ]);

            if (!$rule->exists) {
                $created++;
            } elseif ($rule->isDirty()) {
                $updated++;
            }
            $rule->save();

            // Two rows for one route: the oldest one is kept, the rest stop matching.
            foreach ($existing->slice(1) as $duplicate) {
                if ($duplicate->is_active) {
                    $duplicate->update(['is_active' => false]);
                    $deactivated++;
                }
            }
        }

        foreach ($this->retired() as [$method, $path]) {
            $deactivated += AuthRule::where('service', $service)
                ->where('method', $method)
                ->where('path_dsl', $path)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

        // Rules are cached for 60s per authz:ver; bumping it makes them live now.
        Cache::put('authz:ver', (int) Cache::get('authz:ver', 1) + 1);

        $this->command?->info(sprintf(
            '%s: %d rules (%d created, %d updated, %d deactivated)',
            $service,
            count($this->rules()),
            $created,
            $updated,
            $deactivated
        ));
    }
}
