<?php

namespace Database\Seeders\AuthRules;

/**
 * ScreensPizza: stations, their media, LiveKit tokens and usage monitoring, all under /{storeId}/.
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
            // Managers' browsers report their own Screen Project usage; only
            // screens managers (same as observer) read the monitoring report.
            ['POST', '/*/usage/segments', 'scoped', ['screens user', 'screens manager']],
            ['GET', '/*/usage/segments', 'scoped', ['screens manager']],
        ];
    }
}
