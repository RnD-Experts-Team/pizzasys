<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /auth/refresh-token swaps only the token that called it: a user signed
 * in on two tablets stays signed in on the other one.
 */
class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** Each call authenticates from its own header, not from the guard cached by the previous one. */
    private function asToken(string $token, string $method, string $uri)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token)->json($method, $uri);
    }

    public function test_a_second_token_survives_a_refresh(): void
    {
        $tabletA = $this->user->createToken('auth-token')->plainTextToken;
        $tabletB = $this->user->createToken('auth-token')->plainTextToken;

        $fresh = $this->asToken($tabletA, 'POST', '/api/v1/auth/refresh-token')
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->json('data.token');

        $this->asToken($tabletA, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->asToken($tabletB, 'GET', '/api/v1/auth/me')->assertOk();
        $this->asToken($fresh, 'GET', '/api/v1/auth/me')->assertOk();

        $this->assertSame(2, $this->user->tokens()->where('name', 'auth-token')->count());
    }

    public function test_the_new_token_keeps_the_old_ones_name_and_expires_in_three_days(): void
    {
        $impersonation = $this->user->createToken('impersonation-token')->plainTextToken;
        $login = $this->user->createToken('auth-token')->plainTextToken;

        $fresh = $this->asToken($impersonation, 'POST', '/api/v1/auth/refresh-token')->assertOk()->json('data.token');

        $token = $this->user->tokens()->findOrFail((int) explode('|', $fresh)[0]);
        $this->assertSame('impersonation-token', $token->name);
        $this->assertTrue($token->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));

        $this->assertSame(1, $this->user->tokens()->where('name', 'impersonation-token')->count());
        $this->asToken($login, 'GET', '/api/v1/auth/me')->assertOk();
    }
}
