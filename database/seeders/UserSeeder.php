<?php

namespace Database\Seeders;

use App\Models\AccessManagement\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
//        $admin = User::where('email', 'admin@admin.com')->firstOrCreate();

//        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@admin.com',
                'password' => Hash::make('admin'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['super_admin']);
//        $admin->syncRoles(['admin']);
//
//        // Create editor user
//        $editor = User::firstOrCreate(
//            ['email' => 'editor@example.com'],
//            [
//                'name' => 'Editor User',
//                'email' => 'editor@example.com',
//                'password' => Hash::make('password'),
//                'is_active' => true,
//            ]
//        );
//        $editor->syncRoles(['editor']);
//
//        // Create moderator user
//        $moderator = User::firstOrCreate(
//            ['email' => 'moderator@example.com'],
//            [
//                'name' => 'Moderator User',
//                'email' => 'moderator@example.com',
//                'password' => Hash::make('password'),
//                'is_active' => true,
//            ]
//        );
//        $moderator->syncRoles(['moderator']);
//
//        // Create viewer user
//        $viewer = User::firstOrCreate(
//            ['email' => 'viewer@example.com'],
//            [
//                'name' => 'Viewer User',
//                'email' => 'viewer@example.com',
//                'password' => Hash::make('password'),
//                'is_active' => true,
//            ]
//        );
//        $viewer->syncRoles(['viewer']);
//
//        // Create regular user
//        $user = User::firstOrCreate(
//            ['email' => 'user@example.com'],
//            [
//                'name' => 'Regular User',
//                'email' => 'user@example.com',
//                'password' => Hash::make('password'),
//                'is_active' => true,
//            ]
//        );
//        $user->syncRoles(['user']);
//
//        // Create inactive user
//        $inactive = User::firstOrCreate(
//            ['email' => 'inactive@example.com'],
//            [
//                'name' => 'Inactive User',
//                'email' => 'inactive@example.com',
//                'password' => Hash::make('password'),
//                'is_active' => false,
//            ]
//        );
//        $inactive->syncRoles(['user']);
//
//        $this->command->info('Users seeded successfully!');
//        $this->command->info('Login credentials:');
//        $this->command->info('  Admin:     admin@example.com / password');
//        $this->command->info('  Editor:    editor@example.com / password');
//        $this->command->info('  Moderator: moderator@example.com / password');
//        $this->command->info('  Viewer:    viewer@example.com / password');
//        $this->command->info('  User:      user@example.com / password');
    }
}
