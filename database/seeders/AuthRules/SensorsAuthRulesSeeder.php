<?php

namespace Database\Seeders\AuthRules;

/**
 * Sensors service (external repo, sensors.pnefoods.com). Copied as they are.
 */
class SensorsAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Sensors';
    }

    protected function rules(): array
    {
        return [
            ['GET', '/stores/sensors', 'none', ['mos']],
            ['GET', '/stores/*/sensors', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/reports', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/reports/history', 'scoped', ['reports view', 'mos']],
        ];
    }
}
