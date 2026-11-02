<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlaylistPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'playlists.view', 'playlists.create',
            'playlists.edit', 'playlists.delete',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->givePermissionTo($names);
        }

        Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web'])
            ->givePermissionTo('playlists.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
