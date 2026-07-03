<?php

namespace App\Services;

use App\Models\Menu;
use App\Core\Services\BaseService;

class MenuService extends BaseService
{
    public function create(array $data): Menu
    {
        return $this->transaction(function () use ($data) {
            return Menu::create($data);
        });
    }

    public function update(Menu $menu, array $data): Menu
    {
        return $this->transaction(function () use ($menu, $data) {
            $menu->update($data);

            return $menu;
        });
    }

    public function delete(Menu $menu): void
    {
        $this->transaction(function () use ($menu) {
            Menu::query()
                ->whereKey($menu->id)
                ->delete();
        });
    }
}