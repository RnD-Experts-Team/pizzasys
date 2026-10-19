<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limits, applied by ObservableThrottleRequests (the `throttle` alias).
     *
     * On the auth:sanctum groups Authenticate sorts ahead of the throttle, so a
     * user exists by then; the generic limits are still keyed on a hash of the
     * bearer token, like every other service. The public auth routes key on
     * the IP plus a hash of who they are about - never the raw email.
     *
     * Phase 1: the real limits are generous and the `target:` limits are the
     * numbers we expect to end on - they only log when passed. Phase 2 sets the
     * real limits from those logs and drops the targets.
     *
     * Several windows on one subject need distinct key prefixes (m:, h:, d:),
     * or they share a counter.
     */
    private function configureRateLimiting(): void
    {
        // Authenticated routes: per token, reads and writes counted apart, plus
        // a per-IP ceiling (a store's staff share one NAT address, hence the size).
        RateLimiter::for('api', function (Request $request) {
            $token = self::tokenKey($request);
            $kind = $request->isMethodSafe() ? 'r:' : 'w:';
            $ip = 'ip:'.$request->ip();

            return [
                Limit::perMinute($request->isMethodSafe() ? 600 : 300)->by($kind.$token),
                Limit::perMinute(5000)->by($ip),
                Limit::perMinute($request->isMethodSafe() ? 120 : 60)->by('target:'.$kind.$token),
                Limit::perMinute(1200)->by('target:'.$ip),
            ];
        });

        // The ?search= listings: leading-wildcard LIKE and an uncapped per_page.
        RateLimiter::for('api-heavy', fn (Request $request) => [
            Limit::perMinute(60)->by(self::tokenKey($request)),
            Limit::perMinute(20)->by('target:m:'.self::tokenKey($request)),
            Limit::perHour(200)->by('target:h:'.self::tokenKey($request)),
        ]);

        // Password guessing: per IP and account, plus a per-IP ceiling sized
        // for a store's staff all signing in through one NAT address.
        RateLimiter::for('auth-login', function (Request $request) {
            $who = self::identityKey($request, $request->input('email') ?? $request->input('employee_id'));
            $ip = 'ip:'.$request->ip();

            return [
                Limit::perMinute(10)->by('m:'.$who),
                Limit::perHour(300)->by('h:'.$ip),
                Limit::perMinute(5)->by('target:m:'.$who),
                Limit::perHour(20)->by('target:h:'.$who),
                Limit::perHour(30)->by('target:h:'.$ip),
            ];
        });

        // Every call sends an email.
        RateLimiter::for('auth-password-email', function (Request $request) {
            $who = self::identityKey($request, $request->input('email'));

            return [
                Limit::perMinute(2)->by('m:'.$who),
                Limit::perHour(5)->by('h:'.$who),
                Limit::perDay(15)->by('target:d:'.$who),
            ];
        });

        // A 6-digit code: no reason to be generous. Shared by the check and the
        // reset, which both take a guess. The OTP's own attempts counter stops
        // the guessing when the IP changes.
        RateLimiter::for('auth-otp-verify', function (Request $request) {
            $who = self::identityKey($request, $request->input('email'));

            return [
                Limit::perMinute(5)->by('m:'.$who),
                Limit::perHour(10)->by('h:'.$who),
            ];
        });

        // Every request a satellite serves lands here. Keyed on the token being
        // verified; the caller (the service call token) is only recorded, so
        // Phase 2 can size a per-service cap.
        RateLimiter::for('auth-token-verify', function (Request $request) {
            $token = (string) $request->input('token', '');
            $verified = $token !== '' ? 't:'.hash('sha256', $token) : 'ip:'.$request->ip();

            return [
                Limit::perMinute(3000)->by($verified),
                Limit::perMinute(600)->by('target:'.$verified),
                Limit::perMinute(20000)->by('target:caller:'.self::tokenKey($request)),
            ];
        });

        // Deletes every token the user has. Keyed on the user, not the token:
        // each refresh hands out a new token, which would be a fresh bucket.
        RateLimiter::for('auth-refresh', function (Request $request) {
            $user = $request->user() ? 'u:'.$request->user()->getKey() : self::tokenKey($request);

            return [
                Limit::perMinute(30)->by($user),
                Limit::perMinute(10)->by('target:'.$user),
            ];
        });
    }

    /**
     * The caller's bearer token, hashed - never stored or logged raw - or the
     * IP when there is no token.
     */
    private static function tokenKey(Request $request): string
    {
        $token = (string) $request->bearerToken();

        return $token !== '' ? 't:'.hash('sha256', $token) : 'ip:'.$request->ip();
    }

    /**
     * The IP plus a hash of the account a public auth route is about (email or
     * employee id), so the key - which is logged - carries no address.
     */
    private static function identityKey(Request $request, mixed $identifier): string
    {
        $identifier = is_scalar($identifier) ? mb_strtolower(trim((string) $identifier)) : '';

        return $request->ip().'|'.hash('sha256', $identifier);
    }
}
