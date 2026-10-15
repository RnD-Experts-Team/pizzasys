<?php

namespace Database\Seeders\AuthRules;

/**
 * ScreensPizza: stations, their media, LiveKit tokens and usage recording under /{storeId}/,
 * plus the cross-store monitoring report (stores[] in the query).
 * The station list and station tokens are public (station password), not here.
 */
class ScreensAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Screens';
    }

    protected function rules(): array
    {
        return [
            ['POST', '/*/stations', 'scoped', ['screens user', 'screens manager']],
            ['POST', '/*/stations/**', 'scoped', ['screens user', 'screens manager']],
            ['DELETE', '/*/stations/**', 'scoped', ['screens user', 'screens manager']],
            ['GET', '/*/stations/*/media', 'scoped', ['screens user', 'screens manager']],
            ['POST', '/*/tokens/supervisor', 'scoped', ['screens user', 'screens manager']],
            ['POST', '/*/tokens/observer', 'scoped', ['screens manager']],
            // Managers' browsers report their own Screen Project usage.
            ['POST', '/*/usage/segments', 'scoped', ['screens user', 'screens manager']],
            // The monitoring report, for screens managers (same as observer) at
            // EVERY store in stores[]; the endpoints filter on stores[] only.
            ['GET', '/usage/report', 'scoped', ['screens manager'], 'sources' => ['query' => ['stores']]],
            ['GET', '/usage/timeline', 'scoped', ['screens manager'], 'sources' => ['query' => ['stores']]],
        ];
    }

    protected function retired(): array
    {
        // The per-store raw listing was replaced by /usage/report and /usage/timeline (2026-10-14).
        return [
            ['GET', '/*/usage/segments'],
        ];
    }
}
