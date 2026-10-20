<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ObservableThrottleRequests and the limits in AppServiceProvider.
 *
 * The real limits are far too big to walk past in a test, so most cases swap
 * in a tiny limiter under the same name; the rest check the real closures.
 */
class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE = '/api/v1/auth/me';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = User::factory()->create()->createToken('test')->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token), 'Accept' => 'application/json'];
    }

    private function tinyApiLimiter(int $real, int $target = 100): void
    {
        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute($real)->by('t:'.$request->bearerToken()),
            Limit::perMinute($target)->by('target:t:'.$request->bearerToken()),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function middlewareOf(string $method, string $uri): array
    {
        return Route::getRoutes()->match(Request::create($uri, $method))->gatherMiddleware();
    }

    public function test_passing_a_target_logs_once_and_blocks_nothing(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('throttle')->andReturnSelf();
        $this->tinyApiLimiter(real: 100, target: 2);

        foreach (range(1, 5) as $_) {
            $this->getJson(self::PROBE, $this->headers())->assertOk();
        }

        Log::shouldHaveReceived('warning')
            ->with('throttle.usage', Mockery::on(fn ($ctx) => $ctx['limiter'] === 'api' && $ctx['max'] === 2))
            ->once();
    }

    public function test_record_only_mode_lets_a_breach_through_and_logs_it_once(): void
    {
        config(['app.throttle_enforce' => false]);
        Log::spy();
        Log::shouldReceive('channel')->with('throttle')->andReturnSelf();
        $this->tinyApiLimiter(real: 2);

        $this->getJson(self::PROBE, $this->headers())->assertOk();
        $this->getJson(self::PROBE, $this->headers())->assertOk();

        foreach (range(1, 3) as $_) {
            $this->getJson(self::PROBE, $this->headers())
                ->assertOk()
                ->assertHeader('X-RateLimit-Remaining', '0');
        }

        Log::shouldHaveReceived('warning')
            ->with('throttle.breach', Mockery::on(fn ($ctx) => $ctx['enforced'] === false))
            ->once();
    }

    public function test_enforce_mode_answers_429_with_retry_after(): void
    {
        config(['app.throttle_enforce' => true]);
        $this->tinyApiLimiter(real: 2);

        $this->getJson(self::PROBE, $this->headers())->assertOk();
        $this->getJson(self::PROBE, $this->headers())->assertOk();

        $this->getJson(self::PROBE, $this->headers())
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_two_tokens_do_not_share_a_bucket(): void
    {
        config(['app.throttle_enforce' => true]);
        $this->tinyApiLimiter(real: 1);
        $other = User::factory()->create()->createToken('test')->plainTextToken;

        $this->getJson(self::PROBE, $this->headers())->assertOk();
        $this->getJson(self::PROBE, $this->headers())->assertStatus(429);

        $this->getJson(self::PROBE, $this->headers($other))->assertOk();
    }

    public function test_a_route_tier_stacks_on_the_group_limit(): void
    {
        config(['app.throttle_enforce' => true]);
        RateLimiter::for('api-heavy', fn (Request $request) => Limit::perMinute(1)->by('t:'.$request->bearerToken()));
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage stores', 'web'));
        $headers = $this->headers($admin->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/stores?search=x', $headers)->assertOk();
        $this->getJson('/api/v1/stores?search=x', $headers)->assertStatus(429);
    }

    public function test_an_unreachable_limiter_store_serves_the_request_unthrottled(): void
    {
        config(['app.throttle_enforce' => true]);

        $down = new class extends ArrayStore
        {
            public function get($key)
            {
                throw new \RuntimeException('redis down');
            }

            public function increment($key, $value = 1)
            {
                throw new \RuntimeException('redis down');
            }

            public function put($key, $value, $seconds)
            {
                throw new \RuntimeException('redis down');
            }
        };

        $limiter = new CacheRateLimiter(new Repository($down));
        $limiter->for('api', fn (Request $request) => Limit::perMinute(1)->by('t:'.$request->bearerToken()));
        $this->app->instance(CacheRateLimiter::class, $limiter);

        Log::spy();
        Log::shouldReceive('channel')->with('throttle')->andReturnSelf();

        $this->getJson(self::PROBE, $this->headers())->assertOk();
        $this->getJson(self::PROBE, $this->headers())->assertOk();

        Log::shouldHaveReceived('error')->with('throttle.store_unavailable', Mockery::any())->twice();
    }

    public function test_the_real_api_limits_split_reads_from_writes(): void
    {
        $limits = fn (string $method) => collect(RateLimiter::limiter('api')(
            Request::create(self::PROBE, $method, server: ['HTTP_AUTHORIZATION' => 'Bearer 1|test-token'])
        ));

        $real = fn ($all) => $all->reject(fn (Limit $l) => str_starts_with($l->key, 'target:'))->pluck('maxAttempts')->all();
        $target = fn ($all) => $all->filter(fn (Limit $l) => str_starts_with($l->key, 'target:'))->pluck('maxAttempts')->all();

        $this->assertSame([600, 5000], $real($limits('GET')));
        $this->assertSame([120, 1200], $target($limits('GET')));
        $this->assertSame([300, 5000], $real($limits('POST')));
        $this->assertSame([60, 1200], $target($limits('POST')));
    }

    public function test_keys_hash_the_token_and_fall_back_to_the_ip(): void
    {
        $key = fn (array $server) => RateLimiter::limiter('api-heavy')(Request::create('/x', 'GET', server: $server))[0]->key;

        $a = $key(['HTTP_AUTHORIZATION' => 'Bearer 1|aaa']);

        $this->assertSame($a, $key(['HTTP_AUTHORIZATION' => 'Bearer 1|aaa']));
        $this->assertNotSame($a, $key(['HTTP_AUTHORIZATION' => 'Bearer 1|bbb']));
        $this->assertStringNotContainsString('aaa', $a);
        $this->assertSame('ip:10.1.2.3', $key(['REMOTE_ADDR' => '10.1.2.3']));
    }

    public function test_every_window_on_one_subject_has_its_own_key(): void
    {
        $request = Request::create('/x', 'POST', ['email' => 'a@example.com', 'token' => '1|abc'], server: [
            'HTTP_AUTHORIZATION' => 'Bearer 9|caller',
        ]);

        foreach (['api', 'api-heavy', 'auth-login', 'auth-password-email', 'auth-otp-verify', 'auth-token-verify', 'auth-refresh'] as $name) {
            $keys = collect(RateLimiter::limiter($name)($request))->pluck('key');

            $this->assertSame($keys->count(), $keys->unique()->count(), "{$name} has two limits on one key");
        }
    }

    public function test_the_login_key_mixes_the_ip_and_the_account(): void
    {
        $key = fn (array $input, string $ip = '10.0.0.1') => RateLimiter::limiter('auth-login')(
            Request::create('/api/v1/auth/login', 'POST', $input, server: ['REMOTE_ADDR' => $ip])
        )[0]->key;

        $a = $key(['email' => 'pat@example.com']);

        $this->assertSame($a, $key(['email' => '  PAT@example.com ']));
        $this->assertNotSame($a, $key(['email' => 'pat@example.com'], '10.0.0.2'));
        $this->assertNotSame($a, $key(['email' => 'sam@example.com']));
        $this->assertStringContainsString('10.0.0.1', $a);
        $this->assertStringNotContainsString('pat@', $a);

        // Employees sign in with their employee id.
        $this->assertNotSame($key(['employee_id' => 41]), $key(['employee_id' => 42]));
    }

    public function test_the_login_limit_is_per_ip_and_account(): void
    {
        config(['app.throttle_enforce' => true]);
        User::factory()->create(['email' => 'pat@example.com']);

        $login = fn (string $email, string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password']);

        foreach (range(1, 10) as $_) {
            $login('pat@example.com', '10.0.0.1')->assertStatus(401);
        }

        $login('pat@example.com', '10.0.0.1')->assertStatus(429);
        $login('PAT@example.com', '10.0.0.1')->assertStatus(429);
        $login('pat@example.com', '10.0.0.2')->assertStatus(401);
        $login('sam@example.com', '10.0.0.1')->assertStatus(401);
    }

    public function test_token_verify_carries_only_its_own_limiter(): void
    {
        $middleware = $this->middlewareOf('POST', '/api/v1/auth/token-verify');

        $this->assertContains('throttle:auth-token-verify', $middleware);
        $this->assertNotContains('throttle:api', $middleware);

        // A tiny generic limit does not touch it.
        config(['app.throttle_enforce' => true]);
        $this->tinyApiLimiter(real: 1);

        foreach (range(1, 3) as $_) {
            $this->assertNotSame(429, $this->postJson('/api/v1/auth/token-verify', [], $this->headers())->status());
        }
    }

    public function test_token_verify_keys_on_the_verified_token_and_records_the_caller(): void
    {
        $limits = fn (string $token, string $caller) => collect(RateLimiter::limiter('auth-token-verify')(
            Request::create('/api/v1/auth/token-verify', 'POST', ['token' => $token], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$caller])
        ));

        $a = $limits('1|user-a', '9|svc');

        $this->assertSame([3000, 600, 20000], $a->pluck('maxAttempts')->all());
        $this->assertSame($a[0]->key, $limits('1|user-a', '8|other-svc')[0]->key);
        $this->assertNotSame($a[0]->key, $limits('2|user-b', '9|svc')[0]->key);
        $this->assertSame($a[2]->key, $limits('2|user-b', '9|svc')[2]->key);
        $this->assertStringStartsWith('target:caller:', $a[2]->key);
        $this->assertStringNotContainsString('user-a', $a->pluck('key')->implode(' '));
        $this->assertStringNotContainsString('svc', $a->pluck('key')->implode(' '));
    }

    public function test_the_refresh_limit_follows_the_user_across_new_tokens(): void
    {
        $user = User::factory()->create();
        $key = function (string $token) use ($user) {
            $request = Request::create('/api/v1/auth/refresh-token', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
            $request->setUserResolver(fn () => $user);

            return RateLimiter::limiter('auth-refresh')($request)[0]->key;
        };

        $this->assertSame($key('1|first'), $key('2|second'));
    }

    public function test_the_limiters_sit_on_the_routes_they_are_for(): void
    {
        $this->assertContains('throttle:auth-login', $this->middlewareOf('POST', '/api/v1/auth/login'));
        $this->assertContains('throttle:auth-login', $this->middlewareOf('POST', '/api/v1/auth/employee/login'));
        $this->assertContains('throttle:auth-password-email', $this->middlewareOf('POST', '/api/v1/auth/forgot-password'));
        $this->assertContains('throttle:auth-otp-verify', $this->middlewareOf('POST', '/api/v1/auth/reset-otp-verify'));
        $this->assertContains('throttle:auth-otp-verify', $this->middlewareOf('POST', '/api/v1/auth/reset-password'));
        $this->assertContains('throttle:auth-refresh', $this->middlewareOf('POST', '/api/v1/auth/refresh-token'));

        foreach (['users', 'roles', 'permissions', 'service-clients', 'auth-rules', 'stores', 'employees'] as $listing) {
            $this->assertContains('throttle:api-heavy', $this->middlewareOf('GET', "/api/v1/{$listing}"), $listing);
        }

        // Every authenticated route has the generic limit; no public one does.
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (in_array('auth:sanctum', $middleware, true)) {
                $this->assertContains('throttle:api', $middleware, $route->uri());
            } else {
                $this->assertNotContains('throttle:api', $middleware, $route->uri());
            }
        }
    }
}
