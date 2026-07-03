<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SidebarMenuService
{
    public function getMenus(): Collection
    {
        return Menu::query()
            ->with(['children' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order', 'asc');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function (Menu $menu) {
                $children = $menu->children
                    ->filter(fn (Menu $child) => $this->canViewMenu($child))
                    ->values();

                $menu->setRelation('children', $children);

                return $menu;
            })
            ->filter(function (Menu $menu) {
                return $this->canViewMenu($menu)
                    && ($menu->route || $menu->children->count() > 0);
            })
            ->values();
    }

    private function canViewMenu(Menu $menu): bool
    {
        if (empty($menu->permission)) {
            return true;
        }

        return Gate::allows($menu->permission);
    }
}