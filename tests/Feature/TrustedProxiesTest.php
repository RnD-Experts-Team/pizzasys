<?php

namespace Tests\Feature;

use App\Models\AuthOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * TRUSTED_PROXIES (config/trustedproxy.php): behind the gateway, request->ip()
 * - and so every IP-keyed limit and the events' actor_ip - is the client from
 * X-Forwarded-For, but only when the hop that connects is a trusted proxy.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '10.0.0.9';

    private const CLIENT = '203.0.113.5';

    protected function tearDown(): void
    {
        unset($_SERVER['TRUSTED_PROXIES'], $_ENV['TRUSTED_PROXIES']);

        parent::tearDown();
    }

    /** Sets the env var and reloads the config file that reads it. */
    private function trustProxies(string $value): void
    {
        $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = $value;

        config(['trustedproxy' => require config_path('trustedproxy.php')]);
    }

    private function loginThroughProxy(string $forwardedFor)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeader('X-Forwarded-For', $forwardedFor)
            ->postJson('/api/v1/auth/login', ['email' => 'pat@example.com', 'password' => 'wrong-password']);
    }

    /** Hits counted on one of the auth-login limits (see ObservableThrottleRequests). */
    private function loginHits(string $ip): int
    {
        return RateLimiter::attempts(md5('auth-login'.'h:ip:'.$ip));
    }

    public function test_the_env_var_fills_the_config_and_empty_means_none(): void
    {
        $this->trustProxies('192.168.1.1, 10.0.0.0/8');
        $this->assertSame('192.168.1.1, 10.0.0.0/8', config('trustedproxy.proxies'));

        $this->trustProxies('');
        $this->assertNull(config('trustedproxy.proxies'));
    }

    public function test_a_request_from_a_trusted_proxy_is_limited_under_the_forwarded_ip(): void
    {
        $this->trustProxies('192.168.1.1,10.0.0.0/8');

        $this->loginThroughProxy(self::CLIENT)->assertStatus(401);

        $this->assertSame(1, $this->loginHits(self::CLIENT));
        $this->assertSame(0, $this->loginHits(self::PROXY));
    }

    public function test_two_clients_behind_one_proxy_get_their_own_buckets(): void
    {
        config(['app.throttle_enforce' => true]);
        $this->trustProxies(self::PROXY);

        foreach (range(1, 10) as $_) {
            $this->loginThroughProxy(self::CLIENT)->assertStatus(401);
        }

        $this->loginThroughProxy(self::CLIENT)->assertStatus(429);
        $this->loginThroughProxy('203.0.113.6')->assertStatus(401);
    }

    public function test_without_trust_the_header_is_ignored(): void
    {
        $this->trustProxies('');

        $this->loginThroughProxy(self::CLIENT)->assertStatus(401);

        $this->assertSame(1, $this->loginHits(self::PROXY));
        $this->assertSame(0, $this->loginHits(self::CLIENT));
    }

    public function test_an_untrusted_caller_cannot_forge_its_ip(): void
    {
        $this->trustProxies('192.168.1.1');

        $this->loginThroughProxy(self::CLIENT)->assertStatus(401);

        $this->assertSame(1, $this->loginHits(self::PROXY));
        $this->assertSame(0, $this->loginHits(self::CLIENT));
    }

    public function test_events_record_the_forwarded_ip_as_actor_ip(): void
    {
        Queue::fake();
        $this->trustProxies(self::PROXY);

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('impersonate users', 'web'));
        $target = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders(['X-Forwarded-For' => self::CLIENT, 'Authorization' => 'Bearer '.$admin->createToken('auth-token')->plainTextToken])
            ->postJson("/api/v1/auth/impersonate/{$target->id}")
            ->assertOk();

        $this->assertSame(self::CLIENT, AuthOutboxEvent::sole()->payload['meta']['actor_ip']);
    }
}
