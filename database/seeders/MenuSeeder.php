<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $dashboard = Menu::query()->updateOrCreate(
            ['title' => 'Dashboard', 'parent_id' => null],
            [
                'route' => 'admin.dashboard',
                'icon' => 'dashboard',
                'permission' => 'dashboard.view',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $cmsCore = Menu::query()->updateOrCreate(
            ['title' => 'CMS Core', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'core',
                'permission' => null,
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Users', 'parent_id' => $cmsCore->id],
            [
                'route' => 'admin.users.index',
                'icon' => 'users',
                'permission' => 'users.view',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Companies', 'parent_id' => $cmsCore->id],
            [
                'route' => 'admin.companies.index',
                'icon' => 'building',
                'permission' => 'companies.view',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Roles', 'parent_id' => $cmsCore->id],
            [
                'route' => 'admin.roles.index',
                'icon' => 'shield',
                'permission' => 'roles.view',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Permissions', 'parent_id' => $cmsCore->id],
            [
                'route' => 'admin.permissions.index',
                'icon' => 'key',
                'permission' => 'permissions.view',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Menu Management', 'parent_id' => $cmsCore->id],
            [
                'route' => 'admin.menus.index',
                'icon' => 'menu',
                'permission' => 'menus.view',
                'sort_order' => 5,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Content', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'media',
                'permission' => null,
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Playlist', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'playlist',
                'permission' => null,
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Scheduler', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'calendar',
                'permission' => null,
                'sort_order' => 5,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Devices', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'device',
                'permission' => null,
                'sort_order' => 6,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Monitoring', 'parent_id' => null],
            [
                'route' => null,
                'icon' => 'monitoring',
                'permission' => null,
                'sort_order' => 7,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['title' => 'Settings', 'parent_id' => null],
            [
                'route' => 'admin.settings.index',
                'icon' => 'settings',
                'permission' => 'settings.view',
                'sort_order' => 8,
                'is_active' => true,
            ],
        );
    }
}
