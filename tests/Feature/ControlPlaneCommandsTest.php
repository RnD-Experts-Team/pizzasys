<?php

namespace Tests\Feature;

use App\Models\ServiceClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The two commands the deploy control plane runs on a client's install.
 */
class ControlPlaneCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function stdin(string $value): void
    {
        $this->app->instance('console.stdin', $value);
    }

    public function test_upsert_creates_a_client_whose_token_verifies(): void
    {
        $token = str_repeat('a', 64);
        $this->stdin($token."\n");

        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])
            ->expectsOutput("Service 'Hiring' created.")
            ->doesntExpectOutputToContain($token)
            ->assertSuccessful();

        $client = ServiceClient::where('name', 'Hiring')->sole();
        $this->assertSame(hash('sha256', $token), $client->token_hash);
        $this->assertTrue($client->isActive());
    }

    public function test_upsert_is_idempotent_and_replaces_a_changed_token(): void
    {
        $this->stdin(str_repeat('a', 64));
        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])->assertSuccessful();

        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])
            ->expectsOutput("Service 'Hiring' unchanged.")
            ->assertSuccessful();

        $this->stdin(str_repeat('b', 64));
        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])
            ->expectsOutput("Service 'Hiring' updated.")
            ->assertSuccessful();

        $this->assertSame(1, ServiceClient::count());
        $this->assertSame(hash('sha256', str_repeat('b', 64)), ServiceClient::sole()->token_hash);
    }

    public function test_upsert_reactivates_a_disabled_or_expired_client(): void
    {
        ServiceClient::create(['name' => 'Hiring', 'token_hash' => hash('sha256', str_repeat('a', 64)), 'is_active' => false, 'expires_at' => now()->subDay()]);
        $this->stdin(str_repeat('a', 64));

        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])->assertSuccessful();

        $this->assertTrue(ServiceClient::sole()->isActive());
    }

    public function test_upsert_refuses_a_short_token_or_one_not_on_stdin(): void
    {
        $this->stdin('short');
        $this->artisan('service-client:upsert', ['name' => 'Hiring', '--token-stdin' => true])->assertFailed();
        $this->artisan('service-client:upsert', ['name' => 'Hiring'])->assertFailed();

        $this->assertSame(0, ServiceClient::count());
    }

    public function test_create_admin_makes_a_verified_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'super-admin']);
        $this->stdin('a-long-generated-password');

        $this->artisan('app:create-admin', ['email' => 'Owner@Acme.test', 'name' => 'Owner', '--password-stdin' => true])
            ->assertSuccessful();

        $user = User::where('email', 'owner@acme.test')->sole();
        $this->assertTrue(Hash::check('a-long-generated-password', $user->password));
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_admin_never_resets_an_existing_password(): void
    {
        Role::firstOrCreate(['name' => 'super-admin']);
        $user = User::factory()->create(['email' => 'owner@acme.test', 'password' => Hash::make('the-one-they-chose')]);
        $this->stdin('a-long-generated-password');

        $this->artisan('app:create-admin', ['email' => 'owner@acme.test', 'name' => 'Owner', '--password-stdin' => true])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('the-one-they-chose', $user->password));
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertSame(1, User::count());
    }

    public function test_create_admin_needs_the_role_seeded_first(): void
    {
        $this->stdin('a-long-generated-password');

        $this->artisan('app:create-admin', ['email' => 'owner@acme.test', 'name' => 'Owner', '--password-stdin' => true])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
