<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = \App\Models\Company::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Company',
                'email' => 'admin@cms.test',
                'phone' => null,
                'is_active' => true,
            ],
        );

        $permissions = ['dashboard.view', 'users.view', 'users.create', 'users.edit', 'users.delete', 'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'settings.view', 'settings.edit', 'companies.view', 'companies.create', 'companies.edit', 'companies.delete', 'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete', 'menus.view', 'menus.create', 'menus.edit', 'menus.delete', 'activity_logs.view', 'login_histories.view', 'media.view', 'media.create', 'media.edit', 'media.delete'];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $operator = Role::firstOrCreate(['name' => 'operator']);

        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions(['dashboard.view', 'users.view', 'users.create', 'users.edit', 'roles.view', 'permissions.view', 'menus.view', 'settings.view', 'login_histories.view', 'media.view', 'media.create', 'media.edit', 'media.delete']);

        $operator->syncPermissions(['dashboard.view', 'media.view', 'media.create']);

        $user = User::firstOrCreate(
            ['email' => 'admin@cms.test'],
            [
                'company_id' => $company->id,
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
            ],
        );

        $user->assignRole('super-admin');
    }
}
