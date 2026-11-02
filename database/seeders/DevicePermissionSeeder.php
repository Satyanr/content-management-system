<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DevicePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['devices.view', 'devices.create', 'devices.edit', 'devices.delete', 'devices.activate', 'devices.revoke'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['super-admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->givePermissionTo($names);
        }
        Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web'])->givePermissionTo('devices.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
