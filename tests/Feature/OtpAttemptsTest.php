<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The reset code's own attempts counter: the throttle is per IP, so this is
 * what stops a guesser who keeps changing address.
 */
class OtpAttemptsTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'pat@example.com';

    private const CODE = '123456';

    private Otp $otp;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => self::EMAIL, 'password' => Hash::make('old-password')]);

        $this->otp = Otp::create([
            'email' => self::EMAIL,
            'otp' => self::CODE,
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function check(string $code, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/reset-otp-verify', ['email' => self::EMAIL, 'otp' => $code]);
    }

    private function reset(string $code, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/reset-password', [
            'email' => self::EMAIL,
            'otp' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
    }

    public function test_the_sixth_guess_fails_even_with_the_right_code_from_a_fresh_ip(): void
    {
        config(['app.throttle_enforce' => true]);

        // The throttle allows all five from one address...
        foreach (['000001', '000002', '000003', '000004', '000005'] as $wrong) {
            $this->check($wrong, '10.0.0.1')->assertStatus(422);
        }

        // ...and moving address does not buy a sixth.
        $this->check(self::CODE, '10.0.0.2')->assertStatus(422);
        $this->reset(self::CODE, '10.0.0.3')->assertStatus(400);

        $this->otp->refresh();
        $this->assertSame(5, $this->otp->attempts);
        $this->assertTrue($this->otp->used);
        $this->assertTrue(Hash::check('old-password', User::where('email', self::EMAIL)->value('password')));
    }

    public function test_misses_on_reset_password_count_too(): void
    {
        foreach (['000001', '000002', '000003'] as $i => $wrong) {
            $this->reset($wrong, "10.0.1.{$i}")->assertStatus(400);
        }
        foreach (['000004', '000005'] as $i => $wrong) {
            $this->check($wrong, "10.0.2.{$i}")->assertStatus(422);
        }

        $this->reset(self::CODE, '10.0.3.1')->assertStatus(400);
        $this->assertTrue($this->otp->refresh()->used);
    }

    public function test_a_few_typos_still_let_the_right_code_through(): void
    {
        foreach (['000001', '000002', '000003', '000004'] as $wrong) {
            $this->check($wrong, '10.0.0.1')->assertStatus(422);
        }

        // Checking does not consume the code; the reset does.
        $this->check(self::CODE, '10.0.0.2')->assertOk();
        $this->reset(self::CODE, '10.0.0.2')->assertOk();
        $this->reset(self::CODE, '10.0.0.2')->assertStatus(400);

        $this->assertSame(4, $this->otp->refresh()->attempts);
        $this->assertTrue(Hash::check('new-password', User::where('email', self::EMAIL)->value('password')));
    }

    public function test_an_expired_code_takes_no_attempts(): void
    {
        $this->otp->update(['expires_at' => now()->subMinute()]);

        $this->check('000001', '10.0.0.1')->assertStatus(422);
        $this->check(self::CODE, '10.0.0.1')->assertStatus(422);

        $this->assertSame(0, $this->otp->refresh()->attempts);
    }
}
