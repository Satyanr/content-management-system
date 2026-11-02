<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlaybackPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Menu::firstOrCreate(['route' => 'admin.proof-of-play.index'], ['title' => 'Proof of Play', 'permission' => 'playback.view', 'icon' => 'activity', 'sort_order' => 7, 'is_active' => true]);
        foreach (['devices.assign', 'playback.view', 'playback.export', 'playback.reset'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            // Additive only. No syncPermissions and no grant to view-only operators.
            foreach (Role::whereIn('name', ['super-admin', 'admin'])->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
