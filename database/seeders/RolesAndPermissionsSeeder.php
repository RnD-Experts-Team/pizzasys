<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions based on route middlewares
        $permissions = [
            // User Management Permissions
            'manage users',
            
            // Role Management Permissions  
            'manage roles',
            
            // Permission Management Permissions
            'manage permissions',
            
            // Service Client Management Permissions
            'manage service clients',
            
            // Authorization Rules Management Permissions
            'manage auth rules',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create the super-admin role
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);

        // Give super-admin all permissions
        $superAdmin->givePermissionTo(Permission::all());

        // Create your personal super admin user
        $superAdminUser = User::firstOrCreate(
            ['email' => 'adlermorgan12@gmail.com'],
            [
                'name' => 'Adler Morgan',
                'password' => Hash::make('AdlerMorganNVT123!!'),
                'email_verified_at' => now(), // Already verified
            ]
        );
        
        // Assign super-admin role
        $superAdminUser->assignRole($superAdmin);

        $this->command->info('Super admin seeded successfully:');
        $this->command->info('Email: adlermorgan12@gmail.com');
        $this->command->info('Password: AdlerMorganNVT123!!');
        $this->command->info('Role: super-admin');
        $this->command->info('All permissions granted.');
    }
}
