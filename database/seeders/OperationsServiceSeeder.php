<?php

namespace Database\Seeders;

use App\Models\AuthRule;
use App\Models\ServiceClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Registers the OperationsPizza scheduling service with pizzasys.
 *
 * Creates three things, all idempotent:
 *   1. the scheduling permissions,
 *   2. the `Operations` service client (printing its raw token ONCE),
 *   3. the auth rules that let scheduling requests through.
 *
 * Run with:
 *   php artisan db:seed --class=OperationsServiceSeeder
 *
 * The generated token goes into OperationsPizza's AUTH_SERVER_CALL_TOKEN.
 * Only its sha256 is stored here, so it cannot be recovered later — re-run with
 * OPERATIONS_ROTATE_TOKEN=1 to issue a new one.
 */
class OperationsServiceSeeder extends Seeder
{
    /**
     * The service identity. This ONE string does three jobs and they must all
     * agree with OperationsPizza's AUTH_SERVER_SERVICE_NAME:
     *   - service_clients.name  (ServiceCallerAuthenticator looks it up)
     *   - auth_rules.service    (AuthorizationResolver filters on it)
     *   - the `service` field of the verify request
     */
    private const SERVICE = 'Operations';

    private const PERMISSIONS = [
        'view schedule' => 'Read the schedule: the week grid, roster, availability, time off, templates and publish history.',
        'manage schedule' => 'Create, edit and delete shifts, actual shifts, availability overrides, local time off, templates, and run bulk week operations.',
        'publish schedule' => 'Publish or un-publish a week. Separate because employees are notified by Humanity when a week goes out.',
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedServiceClient();
        $this->seedAuthRules();

        // Rules are cached for 60s under this version key. Bumping it makes the
        // new rules take effect immediately instead of after the TTL.
        Cache::put('authz:ver', (int) Cache::get('authz:ver', 1) + 1);

        $this->command?->info('Bumped authz:ver — rules are live immediately.');
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // Keep super-admin exhaustive, as RolesAndPermissionsSeeder does.
        // (It also bypasses rules entirely via authz.super_roles, but an
        // explicit grant keeps permission listings honest.)
        Role::where('name', 'super-admin')->first()?->givePermissionTo(array_keys(self::PERMISSIONS));

        $this->command?->info('Permissions: ' . implode(', ', array_keys(self::PERMISSIONS)));
        $this->command?->warn(
            'These are NOT attached to any store role yet. Scheduling rules use ' .
            'store_scope_mode=scoped, so a GLOBAL grant does not satisfy them — ' .
            'the permission must come from the role the user holds FOR that store ' .
            '(user_role_store). Attach them to your store manager roles.'
        );
    }

    private function seedServiceClient(): void
    {
        $existing = ServiceClient::where('name', self::SERVICE)->first();
        $rotate = (bool) env('OPERATIONS_ROTATE_TOKEN', false);

        if ($existing && !$rotate) {
            $this->command?->info("Service client '" . self::SERVICE . "' already exists — token left alone.");
            $this->command?->line('  Re-run with OPERATIONS_ROTATE_TOKEN=1 to issue a new token.');

            return;
        }

        // 64 hex chars. Only the hash is persisted.
        $token = bin2hex(random_bytes(32));

        ServiceClient::updateOrCreate(
            ['name' => self::SERVICE],
            [
                'token_hash' => hash('sha256', $token),
                'is_active' => true,
                'expires_at' => null,
                'notes' => 'OperationsPizza — scheduling. Seeded by OperationsServiceSeeder.',
            ]
        );

        $this->command?->newLine();
        $this->command?->warn('╭─ Service token for OperationsPizza ' . ($rotate ? '(ROTATED)' : '(NEW)'));
        $this->command?->warn('│');
        $this->command?->warn('│  AUTH_SERVER_CALL_TOKEN=' . $token);
        $this->command?->warn('│');
        $this->command?->warn('│  Shown ONCE — only its sha256 is stored. Put it in');
        $this->command?->warn('│  OperationsPizza/.env now.');
        $this->command?->warn('╰─');
        $this->command?->newLine();
    }

    /**
     * The rules.
     *
     * Two things about how AuthorizationResolver matches, both easy to get wrong:
     *
     *  - `method` is compared EXACTLY against the request verb
     *    (`where('method', strtoupper($method))`). A rule with method='ANY'
     *    therefore never matches anything — hence one rule per verb below.
     *
     *  - `priority` is ordered ASC and the first match wins, so a LOWER number
     *    means higher precedence. The specific rules sit at 10, the catch-alls
     *    at 100.
     *
     * Route names are matched before paths, so a single-route exception can be
     * added later with `route_name` (e.g. 'api.v1.bulk.clear-week') without
     * disturbing these.
     */
    private function seedAuthRules(): void
    {
        // The middleware sends route parameters under `path`, and our route
        // parameter is literally named `storeId`. The value is the store CODE
        // ("03759-00001"); AuthorizationResolver resolves it to stores.id.
        $storeSources = [
            'path' => ['storeId', 'store_id'],
            'query' => ['store_id', 'store_ids'],
            'body' => ['store_id', 'store_ids'],
            'header' => ['X-Store-Id'],
        ];

        $rules = [
            // ── health ────────────────────────────────────────────────────────
            [
                'method' => 'GET',
                'path_dsl' => '/v1/health',
                'store_scope_mode' => 'none',
                'permissions_any' => null,   // any authenticated subject; it is a smoke test
                'priority' => 10,
                'notes' => 'Auth-chain smoke test.',
            ],

            // ── publishing (own permission: employees get notified) ───────────
            [
                'method' => 'POST',
                'path_dsl' => '/v1/stores/{storeId}/published-schedules',
                'store_scope_mode' => 'scoped',
                'permissions_any' => ['publish schedule'],
                'priority' => 10,
                'notes' => 'Publish a week.',
            ],
            [
                'method' => 'DELETE',
                'path_dsl' => '/v1/stores/{storeId}/published-schedules/{publishedId}',
                'store_scope_mode' => 'scoped',
                'permissions_any' => ['publish schedule'],
                'priority' => 10,
                'notes' => 'Delete a published week.',
            ],

            // ── everything else, by verb ──────────────────────────────────────
            [
                'method' => 'GET',
                'path_dsl' => '/v1/stores/{storeId}/**',
                'store_scope_mode' => 'scoped',
                'permissions_any' => ['view schedule', 'manage schedule'],
                'priority' => 100,
                'notes' => 'All scheduling reads. `manage schedule` implies read.',
            ],
            [
                'method' => 'POST',
                'path_dsl' => '/v1/stores/{storeId}/**',
                'store_scope_mode' => 'scoped',
                'permissions_any' => ['manage schedule'],
                'priority' => 100,
                'notes' => 'All scheduling writes: shifts, actuals, availability, time off, templates, bulk.',
            ],
            [
                'method' => 'DELETE',
                'path_dsl' => '/v1/stores/{storeId}/**',
                'store_scope_mode' => 'scoped',
                'permissions_any' => ['manage schedule'],
                'priority' => 100,
                'notes' => 'All scheduling deletes.',
            ],
        ];

        foreach ($rules as $rule) {
            AuthRule::updateOrCreate(
                [
                    'service' => self::SERVICE,
                    'method' => $rule['method'],
                    'path_dsl' => $rule['path_dsl'],
                ],
                [
                    'route_name' => null,
                    'roles_any' => null,
                    'permissions_any' => $rule['permissions_any'],
                    'permissions_all' => null,
                    'store_scope_mode' => $rule['store_scope_mode'],
                    'store_id_sources' => $rule['store_scope_mode'] === 'scoped' ? $storeSources : null,
                    'store_match_policy' => 'all',
                    'store_allows_empty' => false,
                    // Employees are fail-closed. No employee-facing scheduling
                    // endpoint exists yet; flip this per-rule when one does.
                    'employee_accessible' => false,
                    'is_active' => true,
                    'priority' => $rule['priority'],
                ]
            );
        }

        $this->command?->info('Auth rules: ' . count($rules) . " for service '" . self::SERVICE . "'.");
    }
}
