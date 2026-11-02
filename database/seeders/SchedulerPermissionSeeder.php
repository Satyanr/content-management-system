<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SchedulerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['scheduler.view', 'scheduler.create', 'scheduler.edit', 'scheduler.delete'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['super-admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->givePermissionTo($names);
        }
        Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web'])->givePermissionTo('scheduler.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
