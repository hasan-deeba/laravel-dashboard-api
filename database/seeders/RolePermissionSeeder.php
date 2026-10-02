<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\AccessManagement\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions grouped by resource
        $permissions = [
            // User management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Role management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permission management
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Content management
            'content.view',
            'content.create',
            'content.edit',
            'content.delete',
            'content.publish',

            // Settings
            'settings.view',
            'settings.edit',

            // Reports
            'reports.view',
            'reports.export',

            // Audit logs
            'audit.view',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'cms',
            ]);
        }

        // Create roles and assign permissions

        // Admin role - gets all permissions
        $adminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'cms',
        ]);
        $adminRole->syncPermissions(Permission::all());

        // Editor role - content management
        $editorRole = Role::firstOrCreate([
            'name' => 'editor',
            'guard_name' => 'cms',
        ]);
        $editorRole->syncPermissions([
            'content.view',
            'content.create',
            'content.edit',
            'content.publish',
            'users.view',
        ]);

        // Moderator role - user management and content
        $moderatorRole = Role::firstOrCreate([
            'name' => 'moderator',
            'guard_name' => 'cms',
        ]);
        $moderatorRole->syncPermissions([
            'users.view',
            'users.edit',
            'content.view',
            'content.create',
            'content.edit',
            'reports.view',
            'audit.view',
        ]);

        // Viewer role - read-only access
        $viewerRole = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'cms',
        ]);
        $viewerRole->syncPermissions([
            'users.view',
            'content.view',
            'reports.view',
            'settings.view',
        ]);

        // User role - basic user
        Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'cms',
        ]);
        // No permissions for basic user

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Roles: super_admin, editor, moderator, viewer, user');
        $this->command->info('Total permissions: ' . count($permissions));
    }
}
