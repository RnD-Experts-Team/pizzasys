<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * The permission and role catalog. Additive: creates what is missing and adds
 * grants, never removes anything.
 *
 * A permission is checked globally or per store depending on the auth rule
 * (database/seeders/AuthRules). A role assigned to a user directly is global;
 * the same role assigned for a store (user_role_store) grants its permissions
 * at that store only.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        // pizzasys administration (Spatie middleware on pizzasys's own routes)
        'manage users',
        'manage roles',
        'manage permissions',
        'manage service clients',
        'manage auth rules',
        'manage stores',
        'manage user role assignments',
        'manage role hierarchy',
        'manage employees',
        'manage employee role assignments',
        'impersonate users',

        // Data
        'export data',
        'reports view',
        'keys handling',
        'data entry',
        'import data',

        // QA
        'qa audit',
        'qa admin',
        'cleaning specialist',
        'dough and sauce',

        // Hiring
        'hiring specialist',
        'hiring admin',
        'employee obsession',

        // Notifications, Screens, Maintenance, Inventory
        'manage announcements',
        'screens user',
        'screens manager',
        'mos',
        'inventory handling',

        // Operations (scheduling)
        'view schedule',
        'manage schedule',

        // Toolbox
        'view tickets',
        'administer tickets',
        'create workbooks',
    ];

    /** Role => permissions it grants. super-admin gets everything. */
    private const ROLES = [
        'QA Manager' => ['qa audit', 'qa admin'],
        'QA Auditor' => ['qa audit'],
        'Store Manager' => [
            'reports view',
            'data entry',
            'inventory handling',
            'cleaning specialist',
            'view schedule',
            'manage schedule',
            'view tickets',
            'create workbooks',
        ],
        'Hiring Manager' => ['hiring specialist', 'hiring admin'],
        'Hiring Specialist' => ['hiring specialist'],
        'MOS' => ['mos'],
        'Employee Obsession' => ['employee obsession'],
        'Inventory Handler' => ['inventory handling'],
        'Screens User' => ['screens user'],
        'Cleaning Specialist' => ['cleaning specialist'],
        'Dough and Sauce' => ['dough and sauce'],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::firstOrCreate(['name' => $role])->givePermissionTo($permissions);
        }

        // super-admin bypasses every rule anyway; holding every permission keeps
        // permission listings honest.
        Role::firstOrCreate(['name' => 'super-admin'])->givePermissionTo(Permission::all());

        $this->command?->info(sprintf(
            'Roles and permissions: %d permissions, %d roles.',
            count(self::PERMISSIONS),
            count(self::ROLES) + 1
        ));
    }
}
