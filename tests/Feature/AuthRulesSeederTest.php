<?php

namespace Tests\Feature;

use App\Models\AuthRule;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Services\V1\Auth\AuthorizationResolver;
use Database\Seeders\AuthRulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthRulesSeederTest extends TestCase
{
    use RefreshDatabase;

    private Store $storeA;
    private Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        // The resolver caches through the redis store explicitly.
        config(['cache.stores.redis' => ['driver' => 'array']]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AuthRulesSeeder::class);

        $this->storeA = Store::create(['store_id' => '03795-00001', 'name' => 'Store A', 'is_active' => true]);
        $this->storeB = Store::create(['store_id' => '03795-00002', 'name' => 'Store B', 'is_active' => true]);
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $before = AuthRule::orderBy('id')->get(['id', 'permissions_any', 'store_scope_mode', 'is_active'])->toArray();

        $this->seed(AuthRulesSeeder::class);

        $this->assertSame($before, AuthRule::orderBy('id')->get(['id', 'permissions_any', 'store_scope_mode', 'is_active'])->toArray());
    }

    public function test_duplicate_rows_for_one_route_are_deactivated_not_deleted(): void
    {
        AuthRule::create(['service' => 'QA', 'method' => 'PATCH', 'path_dsl' => '/custom-reports/*', 'permissions_any' => ['qa audit'], 'store_scope_mode' => 'scoped']);

        $this->seed(AuthRulesSeeder::class);

        $rows = AuthRule::where('service', 'QA')->where('method', 'PATCH')->where('path_dsl', '/custom-reports/*')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->where('is_active', true)->count());
        $this->assertSame(['qa admin'], $rows->firstWhere('is_active', true)->permissions_any);
    }

    public function test_store_manager_runs_the_schedule_of_their_own_store_only(): void
    {
        $manager = $this->userWithStoreRole('Store Manager', $this->storeA);

        $this->assertTrue($this->allows($manager, 'Operations', 'GET', '/v1/stores/03795-00001/schedule/week', ['storeId' => '03795-00001']));
        $this->assertTrue($this->allows($manager, 'Operations', 'POST', '/v1/stores/03795-00001/employees/7/clock-in', ['storeId' => '03795-00001']));
        $this->assertFalse($this->allows($manager, 'Operations', 'GET', '/v1/stores/03795-00002/schedule/week', ['storeId' => '03795-00002']));
    }

    public function test_locked_maintenance_notes_are_mos_only(): void
    {
        $mos = $this->userWithStoreRole('MOS', $this->storeA);
        $manager = $this->userWithStoreRole('Store Manager', $this->storeA);
        $path = ['store' => $this->storeA->id];

        $this->assertTrue($this->allows($mos, 'Maintenance', 'GET', "/stores/{$this->storeA->id}/tickets/5/notes/all", $path));
        $this->assertTrue($this->allows($manager, 'Maintenance', 'GET', "/stores/{$this->storeA->id}/tickets/5/notes", $path));
        $this->assertFalse($this->allows($manager, 'Maintenance', 'GET', "/stores/{$this->storeA->id}/tickets/5/notes/all", $path));
    }

    public function test_announcements_are_a_global_permission_and_the_feed_is_open(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage announcements');
        $plain = User::factory()->create();

        $this->assertTrue($this->allows($admin, 'Notifications', 'POST', '/announcements'));
        $this->assertFalse($this->allows($plain, 'Notifications', 'POST', '/announcements'));
        $this->assertTrue($this->allows($plain, 'Notifications', 'GET', '/announcements/visible'));
    }

    public function test_toolbox_leaves_tickets_to_toolbox_but_gates_the_queue_and_catalog(): void
    {
        $plain = User::factory()->create();
        $manager = $this->userWithStoreRole('Store Manager', $this->storeA);
        $queue = ['storeId' => '03795-00001'];

        $this->assertTrue($this->allows($plain, 'Toolbox', 'GET', '/v1/tickets'));
        $this->assertTrue($this->allows($plain, 'Toolbox', 'POST', '/v1/stores/03795-00001/tickets/9/responses', $queue));
        $this->assertFalse($this->allows($plain, 'Toolbox', 'GET', '/v1/stores/03795-00001/tickets', $queue));
        $this->assertTrue($this->allows($manager, 'Toolbox', 'GET', '/v1/stores/03795-00001/tickets', $queue));
        $this->assertTrue($this->allows($manager, 'Toolbox', 'POST', '/v1/stores/03795-00001/workbook-folders', $queue));
        $this->assertFalse($this->allows($manager, 'Toolbox', 'POST', '/v1/stores/03795-00002/workbook-folders', ['storeId' => '03795-00002']));
        $this->assertFalse($this->allows($manager, 'Toolbox', 'POST', '/v1/ticket-levels'));
    }

    public function test_global_dough_and_sauce_specialist_passes_scoped_dough_routes(): void
    {
        $specialist = User::factory()->create();
        $specialist->assignRole('Dough and Sauce');
        $manager = $this->userWithStoreRole('Store Manager', $this->storeA);

        $this->assertTrue($this->allows($specialist, 'QA', 'GET', '/stores/03795-00002/dough-sauce/week', ['store_id' => '03795-00002']));
        $this->assertTrue($this->allows($specialist, 'Inventory', 'GET', '/inventory/counts'));
        $this->assertFalse($this->allows($manager, 'Inventory', 'GET', '/inventory/counts'));
        $this->assertTrue($this->allows($manager, 'Inventory', 'GET', '/inventory/stores/03795-00001/counts', ['store_id' => '03795-00001']));
    }

    public function test_employee_tokens_match_no_rule(): void
    {
        $employee = Employee::create(['id' => 501, 'first_name' => 'Sam', 'last_name' => 'Lee', 'active' => true, 'password' => 'secret']);

        $this->assertFalse($this->allows($employee, 'Toolbox', 'GET', '/v1/tickets'));
        $this->assertFalse($this->allows($employee, 'Operations', 'GET', '/v1/health'));
    }

    private function userWithStoreRole(string $role, Store $store): User
    {
        $user = User::factory()->create();
        $user->storeRoles()->attach(Role::findByName($role)->id, ['store_id' => $store->id, 'is_active' => true]);

        return $user;
    }

    private function allows(Model $actor, string $service, string $method, string $path, array $pathParams = []): bool
    {
        [$authorized] = app(AuthorizationResolver::class)->check(
            $service, $method, $path, null, [], [], [],
            ['path' => $pathParams, 'query' => [], 'body' => [], 'header' => []],
            $actor
        );

        return $authorized;
    }
}
