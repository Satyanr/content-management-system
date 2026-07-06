<?php

namespace App\Services;

use App\Models\Menu;
use App\Core\Services\BaseService;

class MenuService extends BaseService
{
    public function create(array $data): Menu
    {
        return $this->transaction(function () use ($data) {
            $menu = Menu::query()->create($data);

            $this->activityLog(
                action: 'created',
                module: 'menus',
                description: 'Created menu ' . $menu->title,
                subject: $menu,
                newValues: [
                    'id' => $menu->id,
                    'parent_id' => $menu->parent_id,
                    'title' => $menu->title,
                    'route' => $menu->route,
                    'icon' => $menu->icon,
                    'permission' => $menu->permission,
                    'sort_order' => $menu->sort_order,
                    'is_active' => $menu->is_active,
                ],
            );

            return $menu;
        });
    }

    public function update(Menu $menu, array $data): Menu
    {
        return $this->transaction(function () use ($menu, $data) {
            $oldValues = [
                'parent_id' => $menu->parent_id,
                'title' => $menu->title,
                'route' => $menu->route,
                'icon' => $menu->icon,
                'permission' => $menu->permission,
                'sort_order' => $menu->sort_order,
                'is_active' => $menu->is_active,
            ];

            $menu->update($data);

            $this->activityLog(
                action: 'updated',
                module: 'menus',
                description: 'Updated menu ' . $menu->title,
                subject: $menu,
                oldValues: $oldValues,
                newValues: [
                    'parent_id' => $menu->parent_id,
                    'title' => $menu->title,
                    'route' => $menu->route,
                    'icon' => $menu->icon,
                    'permission' => $menu->permission,
                    'sort_order' => $menu->sort_order,
                    'is_active' => $menu->is_active,
                ],
            );

            return $menu;
        });
    }

    public function delete(Menu $menu): void
    {
        $this->transaction(function () use ($menu) {
            $oldValues = [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'title' => $menu->title,
                'route' => $menu->route,
                'icon' => $menu->icon,
                'permission' => $menu->permission,
                'sort_order' => $menu->sort_order,
                'is_active' => $menu->is_active,
            ];

            $this->activityLog(action: 'deleted', module: 'menus', description: 'Deleted menu ' . $menu->title, subject: $menu, oldValues: $oldValues);

            Menu::query()->whereKey($menu->id)->delete();
        });
    }
}
