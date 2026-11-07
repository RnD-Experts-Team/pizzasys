<?php

namespace Tests\Feature;

use App\Models\AuthOutboxEvent;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRoleStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * POST /user-role-store/apply: several store-access changes for one user in
 * one transaction - all of them, each with its own outbox event, or none.
 */
class UserRoleStoreApplyTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;

    private User $user;

    private Role $manager;

    private Role $hiring;

    private Store $storeA;

    private Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage user role assignments', 'web'));
        $this->headers = ['Authorization' => 'Bearer '.$admin->createToken('auth-token')->plainTextToken];

        $this->user = User::factory()->create();
        $this->manager = Role::create(['name' => 'Store Manager']);
        $this->hiring = Role::create(['name' => 'Hiring']);
        $this->storeA = Store::create(['store_id' => '03795-00001', 'name' => 'Store A', 'is_active' => true]);
        $this->storeB = Store::create(['store_id' => '03795-00002', 'name' => 'Store B', 'is_active' => true]);

        // Already held: Store Manager at A (active), Hiring at B (to be removed).
        $this->grant($this->manager, $this->storeA);
        $this->grant($this->hiring, $this->storeB);
    }

    private function grant(Role $role, Store $store, bool $active = true): void
    {
        UserRoleStore::create([
            'user_id' => $this->user->id, 'role_id' => $role->id, 'store_id' => $store->id, 'is_active' => $active,
        ]);
    }

    private function op(string $op, Role $role, Store $store, array $extra = []): array
    {
        return ['op' => $op, 'role_id' => $role->id, 'store_id' => $store->id] + $extra;
    }

    private function apply(array $ops)
    {
        return $this->postJson('/api/v1/user-role-store/apply', ['user_id' => $this->user->id, 'ops' => $ops], $this->headers);
    }

    /** @return array<int, string> the outbox subjects, without any environment prefix */
    private function events(): array
    {
        return AuthOutboxEvent::pluck('subject')
            ->map(fn ($s) => str_replace('auth.testing.v1.', 'auth.v1.', $s))
            ->all();
    }

    private function snapshot(): array
    {
        return UserRoleStore::orderBy('id')->get(['user_id', 'role_id', 'store_id', 'is_active', 'metadata'])->toArray();
    }

    public function test_every_op_applies_with_one_event_each_and_the_answer_matches_user_assignments(): void
    {
        $response = $this->apply([
            $this->op('assign', $this->hiring, $this->storeA, ['metadata' => ['note' => 'cover'], 'is_active' => false]),
            $this->op('toggle', $this->manager, $this->storeA, ['is_active' => false]),
            $this->op('assign', $this->manager, $this->storeB),
            $this->op('remove', $this->hiring, $this->storeB),
        ]);

        $response->assertOk()->assertJsonPath('success', true)->assertJsonCount(3, 'data.assignments');

        $rows = UserRoleStore::where('user_id', $this->user->id)->get()
            ->mapWithKeys(fn ($r) => ["{$r->role_id}@{$r->store_id}" => $r]);

        $this->assertFalse($rows["{$this->hiring->id}@{$this->storeA->id}"]->is_active);
        $this->assertSame(['note' => 'cover'], $rows["{$this->hiring->id}@{$this->storeA->id}"]->metadata);
        $this->assertFalse($rows["{$this->manager->id}@{$this->storeA->id}"]->is_active);
        $this->assertTrue($rows["{$this->manager->id}@{$this->storeB->id}"]->is_active);
        $this->assertArrayNotHasKey("{$this->hiring->id}@{$this->storeB->id}", $rows->all());

        $this->assertEqualsCanonicalizing([
            'auth.v1.assignment.user_role_store.assigned',
            'auth.v1.assignment.user_role_store.toggled',
            'auth.v1.assignment.user_role_store.assigned',
            'auth.v1.assignment.user_role_store.removed',
        ], $this->events());

        // Same shape as GET /user-role-store/user-assignments, inactive rows included.
        $listed = $this->getJson("/api/v1/user-role-store/user-assignments?user_id={$this->user->id}", $this->headers)->json('data.assignments');
        $this->assertSame($listed, $response->json('data.assignments'));
    }

    public function test_one_failing_op_rolls_back_the_ones_before_it(): void
    {
        $before = $this->snapshot();

        $this->apply([
            $this->op('assign', $this->hiring, $this->storeA),
            $this->op('remove', $this->manager, $this->storeA),
            $this->op('toggle', $this->hiring, $this->storeA),
            $this->op('toggle', $this->manager, $this->storeB),
        ])->assertUnprocessable()->assertJsonValidationErrors('ops.3');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame([], $this->events());
    }

    public function test_an_assignment_that_already_exists_fails_the_batch(): void
    {
        $before = $this->snapshot();

        $this->apply([
            $this->op('remove', $this->hiring, $this->storeB),
            $this->op('assign', $this->manager, $this->storeA),
        ])->assertUnprocessable()->assertJsonValidationErrors('ops.1');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame([], $this->events());
    }

    public function test_a_toggle_to_the_state_the_row_is_already_in_fails(): void
    {
        $this->apply([$this->op('toggle', $this->manager, $this->storeA, ['is_active' => true])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ops.0');

        $this->assertTrue(UserRoleStore::where('role_id', $this->manager->id)->sole()->is_active);
    }

    public function test_the_body_is_validated(): void
    {
        $this->apply([])->assertUnprocessable()->assertJsonValidationErrors('ops');

        $this->apply([
            ['op' => 'rename', 'role_id' => $this->manager->id, 'store_id' => $this->storeA->id],
            $this->op('remove', $this->hiring, $this->storeB, ['metadata' => ['x' => 1]]),
            $this->op('remove', $this->hiring, $this->storeB, ['is_active' => true]),
            ['op' => 'assign', 'role_id' => 999, 'store_id' => $this->storeA->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ops.0.op', 'ops.1.metadata', 'ops.2.is_active', 'ops.3.role_id']);

        $this->assertSame([], $this->events());
    }

    public function test_it_needs_the_assignment_permission(): void
    {
        $other = User::factory()->create()->createToken('auth-token')->plainTextToken;

        $this->postJson('/api/v1/user-role-store/apply', [
            'user_id' => $this->user->id,
            'ops' => [$this->op('remove', $this->hiring, $this->storeB)],
        ], ['Authorization' => 'Bearer '.$other])->assertForbidden();

        $this->assertSame(2, UserRoleStore::count());
    }
}
