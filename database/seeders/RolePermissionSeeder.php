<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing roles and permissions
        Permission::truncate();
        Role::truncate();

        // Define permissions with api guard
        $permissions = [
            // User management
            'view users',
            'create users',
            'update users',
            'delete users',

            // Role management
            'view roles',
            'create roles',
            'update roles',
            'delete roles',

            // Permission management
            'view permissions',
            'create permissions',
            'update permissions',
            'delete permissions',

            // Tenant management
            'view tenants',
            'create tenants',
            'update tenants',
            'delete tenants',

            // Campaign management
            'view campaigns',
            'create campaigns',
            'update campaigns',
            'delete campaigns',

            // Task management
            'view tasks',
            'create tasks',
            'update tasks',
            'delete tasks',
        ];

        // Create permissions with api guard
        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);

        $userRole = Role::create([
            'name' => 'user',
            'guard_name' => 'api',
        ]);

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // User gets basic permissions
        $userRole->givePermissionTo([
            'view users',
            'update users',
            'view roles',
            'view permissions',
            'view tenants',
            'view campaigns',
            'create campaigns',
            'update campaigns',
            'view tasks',
            'create tasks',
            'update tasks',
        ]);
    }
}
