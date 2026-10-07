<?php

namespace Database\Seeders;

use Database\Seeders\AuthRules\DataAuthRulesSeeder;
use Database\Seeders\AuthRules\HiringAuthRulesSeeder;
use Database\Seeders\AuthRules\InventoryAuthRulesSeeder;
use Database\Seeders\AuthRules\MaintenanceAuthRulesSeeder;
use Database\Seeders\AuthRules\NotificationsAuthRulesSeeder;
use Database\Seeders\AuthRules\OperationsAuthRulesSeeder;
use Database\Seeders\AuthRules\QaAuthRulesSeeder;
use Database\Seeders\AuthRules\ScreensAuthRulesSeeder;
use Database\Seeders\AuthRules\SensorsAuthRulesSeeder;
use Database\Seeders\AuthRules\ToolboxAuthRulesSeeder;
use Illuminate\Database\Seeder;

/**
 * Every service's auth rules. Additive: upserts, never deletes.
 * Seed RolesAndPermissionsSeeder first so the permissions exist.
 *
 *   php artisan db:seed --class=AuthRulesSeeder
 *
 * Conventions and row format: Database\Seeders\AuthRules\AuthRuleSeeder.
 */
class AuthRulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DataAuthRulesSeeder::class,
            HiringAuthRulesSeeder::class,
            InventoryAuthRulesSeeder::class,
            MaintenanceAuthRulesSeeder::class,
            NotificationsAuthRulesSeeder::class,
            OperationsAuthRulesSeeder::class,
            QaAuthRulesSeeder::class,
            ScreensAuthRulesSeeder::class,
            SensorsAuthRulesSeeder::class,
            ToolboxAuthRulesSeeder::class,
        ]);
    }
}
