<?php

namespace Tests\Feature;

use App\Models\AuthRule;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Named, ability-restricted tokens minted for scheduled reports.
 */
class ReportScheduleTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $login;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports view', 'sales view', 'pay view', 'unrelated'] as $name) {
            Permission::create(['name' => $name]);
        }

        $rules = [
            ['/report-datasets/data.daily_sales/query', ['reports view'], null],
            ['/report-datasets/hiring.pay/query', ['pay view'], null],
            ['/report-builder/preview', null, ['sales view']],
        ];

        foreach ($rules as [$path, $any, $all]) {
            AuthRule::create([
                'service' => 'Data', 'method' => 'POST', 'path_dsl' => $path,
                'permissions_any' => $any, 'permissions_all' => $all, 'store_scope_mode' => 'none',
            ]);
        }

        // Same kind of permission, but on a route the schedule has nothing to do with.
        AuthRule::create([
            'service' => 'Data', 'method' => 'GET', 'path_dsl' => '/other/thing',
            'permissions_any' => ['unrelated'], 'store_scope_mode' => 'none',
        ]);

        // Inactive rules grant nothing.
        AuthRule::create([
            'service' => 'Data', 'method' => 'POST', 'path_dsl' => '/report-datasets/old/query',
            'permissions_any' => ['pay view'], 'store_scope_mode' => 'none', 'is_active' => false,
        ]);

        $this->user = User::factory()->create();
        $this->login = $this->user->createToken('auth-token')->plainTextToken;
    }

    /** Each call authenticates from its own header, not from the guard cached by the previous one. */
    private function asToken(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token)->json($method, $uri, $data);
    }

    private function scheduleTokens(int $id)
    {
        return $this->user->tokens()->where('name', "report-schedule:$id")->get();
    }

    public function test_mint_limits_abilities_to_held_permissions_named_by_report_rules(): void
    {
        $this->user->givePermissionTo('reports view', 'unrelated');

        $store = Store::create(['store_id' => '03795-00001', 'name' => 'Store A', 'is_active' => true]);
        $role = Role::create(['name' => 'Store Role']);
        $role->givePermissionTo('sales view');
        $this->user->storeRoles()->attach($role->id, ['store_id' => $store->id, 'is_active' => true]);

        $response = $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);

        $token = $this->scheduleTokens(7)->sole();
        $this->assertEqualsCanonicalizing(['reports view', 'sales view'], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()));
        $this->assertSame($token->expires_at->toIso8601String(), $response->json('data.expires_at'));
    }

    public function test_mint_falls_back_to_a_non_wildcard_ability(): void
    {
        $this->user->givePermissionTo('unrelated');

        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7])
            ->assertCreated();

        $this->assertSame(['report-builder'], $this->scheduleTokens(7)->sole()->abilities);
    }

    public function test_minting_again_replaces_the_old_token(): void
    {
        $first = $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7])
            ->json('data.token');
        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 8]);
        $second = $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7])
            ->json('data.token');

        $this->assertNotSame($first, $second);
        $this->assertCount(1, $this->scheduleTokens(7));
        $this->assertCount(1, $this->scheduleTokens(8));
        $this->assertSame((int) explode('|', $second)[0], $this->scheduleTokens(7)->first()->id);
    }

    public function test_mint_requires_a_schedule_id(): void
    {
        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_id');
    }

    public function test_delete_removes_only_that_schedules_token_and_is_idempotent(): void
    {
        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7]);
        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 8]);

        $this->asToken($this->login, 'DELETE', '/api/v1/auth/report-schedule-tokens/7')->assertNoContent();
        $this->asToken($this->login, 'DELETE', '/api/v1/auth/report-schedule-tokens/7')->assertNoContent();

        $this->assertCount(0, $this->scheduleTokens(7));
        $this->assertCount(1, $this->scheduleTokens(8));
        $this->assertSame(1, $this->user->tokens()->where('name', 'auth-token')->count());
    }

    public function test_dashboard_refresh_keeps_schedule_tokens_and_replaces_the_login_token(): void
    {
        $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7]);

        $this->asToken($this->login, 'POST', '/api/v1/auth/refresh-token')->assertOk();

        $this->assertCount(1, $this->scheduleTokens(7));
        $this->assertSame(1, $this->user->tokens()->where('name', 'auth-token')->count());
        $this->assertSame(2, $this->user->tokens()->count());
    }

    public function test_a_restricted_token_is_refused_on_pizzasys_user_routes(): void
    {
        $this->user->givePermissionTo('reports view');

        $schedule = $this->asToken($this->login, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 7])
            ->json('data.token');

        $this->asToken($schedule, 'GET', '/api/v1/auth/me')->assertForbidden();
        $this->asToken($schedule, 'POST', '/api/v1/auth/refresh-token')->assertForbidden();
        $this->asToken($schedule, 'POST', '/api/v1/auth/report-schedule-tokens', ['schedule_id' => 9])->assertForbidden();

        $this->assertCount(1, $this->scheduleTokens(7));
        $this->assertCount(0, $this->scheduleTokens(9));
    }

    public function test_the_login_token_still_reaches_user_routes(): void
    {
        $this->asToken($this->login, 'GET', '/api/v1/auth/me')->assertOk();
    }
}
