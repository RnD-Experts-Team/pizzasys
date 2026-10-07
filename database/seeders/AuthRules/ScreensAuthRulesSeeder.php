<?php

namespace Database\Seeders\AuthRules;

/**
 * ScreensPizza: stations, their media and LiveKit tokens, all under /{storeId}/.
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
        ];
    }
}
