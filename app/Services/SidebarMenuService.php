<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class SidebarMenuService
{
    public function getMenus(): Collection
    {
        $filter = function (Collection $menus) use (&$filter): Collection {
            return $menus->filter(fn (Menu $menu) => $menu->is_active && $this->canViewMenu($menu))
                ->map(function (Menu $menu) use (&$filter) {
                    $menu->setRelation('children', $filter($menu->children));

                    return $menu;
                })->filter(fn (Menu $menu) => $this->hasLink($menu) || $menu->children->isNotEmpty())->values();
        };

        return $filter(Menu::query()->with('children')->whereNull('parent_id')->orderBy('sort_order')->get());
    }

    private function hasLink(Menu $menu): bool
    {
        if (! $menu->route || ! Route::has($menu->route)) {
            return false;
        }
        $route = Route::getRoutes()->getByName($menu->route);
        // Sidebar links cannot provide required route parameters.
        foreach ($route->parameterNames() as $parameter) {
            if (! array_key_exists($parameter, $route->defaults) && ! str_contains($route->uri(), '{'.$parameter.'?}')) {
                return false;
            }
        }

        return in_array('GET', $route->methods(), true);
    }

    private function canViewMenu(Menu $menu): bool
    {
        if ($menu->permission && ! Gate::allows($menu->permission)) {
            return false;
        }
        if ($menu->route && Route::has($menu->route)) {
            foreach (Route::getRoutes()->getByName($menu->route)->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'permission:') && ! Gate::any(explode('|', explode(',', substr($middleware, 11))[0]))) {
                    return false;
                }
            }
        }

        return true;
    }

    public function groups(): array
    {
        $groups = ['dashboard' => [], 'operational' => [], 'administration' => [], 'custom' => []];
        foreach ($this->getMenus() as $menu) {
            // Only unwrap the original structural containers; edited/custom containers keep their hierarchy.
            $entries = ! $menu->route && in_array($menu->title, ['CMS Core', 'Content'], true) ? $menu->children : collect([$menu]);
            foreach ($entries as $entry) {
                $key = match ($entry->route) {
                    'admin.dashboard' => 'dashboard',
                    'admin.media-assets.index', 'admin.playlists.index', 'admin.scheduler.index', 'admin.devices.index', 'admin.proof-of-play.index' => 'operational',
                    'admin.users.index', 'admin.companies.index', 'admin.roles.index', 'admin.permissions.index', 'admin.menus.index', 'admin.settings.index', 'admin.activity-logs.index', 'admin.login-histories.index' => 'administration',
                    default => 'custom',
                };
                $groups[$key][] = $this->present($entry);
            }
        }

        return $groups;
    }

    private function present(Menu $menu): array
    {
        $labels = ['Dashboard' => 'Dashboard', 'Content Library' => 'Konten', 'Playlist' => 'Playlist', 'Scheduler' => 'Jadwal Tayang', 'Devices' => 'Perangkat', 'Users' => 'Pengguna', 'Companies' => 'Perusahaan', 'Roles' => 'Hak Akses / Peran', 'Permissions' => 'Hak Akses / Izin', 'Menu Management' => 'Manajemen Menu', 'Settings' => 'Pengaturan', 'Activity Logs' => 'Log Aktivitas', 'Login History' => 'Riwayat Login'];
        $icons = ['dashboard' => 'dashboard', 'image' => 'image', 'media' => 'image', 'playlist' => 'video', 'calendar' => 'calendar', 'device' => 'monitor', 'users' => 'users', 'building' => 'building', 'shield' => 'shield', 'key' => 'shield', 'menu' => 'menu', 'settings' => 'settings', 'activity' => 'activity', 'history' => 'clock'];
        $children = $menu->children->map(fn ($child) => $this->present($child))->all();
        $prefix = $menu->route ? preg_replace('/\.index$/', '', $menu->route) : null;
        $active = $prefix && (request()->routeIs($menu->route) || request()->routeIs($prefix.'.*'));

        return ['id' => $menu->id, 'label' => $labels[$menu->title] ?? $menu->title, 'icon' => $icons[$menu->icon] ?? 'page',
            'href' => $this->hasLink($menu) ? route($menu->route) : null,
            'active' => $active || collect($children)->contains('active', true), 'children' => $children];
    }
}
