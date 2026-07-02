<?php

namespace App\Livewire\Menus;

use App\Models\Menu;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

class MenuTable extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEdit = false;

    public ?int $menuId = null;
    public ?int $parent_id = null;
    public string $title = '';
    public ?string $route = null;
    public ?string $icon = null;
    public ?string $permission = null;
    public int $sort_order = 0;
    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function resetForm(): void
    {
        $this->reset('menuId', 'parent_id', 'title', 'route', 'icon', 'permission', 'sort_order', 'is_active', 'isEdit');

        $this->is_active = true;
        $this->sort_order = 0;

        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $menu = Menu::findOrFail($id);

        $this->menuId = $menu->id;
        $this->parent_id = $menu->parent_id;
        $this->title = $menu->title;
        $this->route = $menu->route;
        $this->icon = $menu->icon;
        $this->permission = $menu->permission;
        $this->sort_order = $menu->sort_order;
        $this->is_active = $menu->is_active;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $message = $this->isEdit ? 'Menu updated successfully.' : 'Menu created successfully.';

        $this->validate([
            'parent_id' => ['nullable', 'exists:menus,id'],
            'title' => ['required', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'is_active' => ['boolean'],
        ]);

        Menu::updateOrCreate(
            ['id' => $this->menuId],
            [
                'parent_id' => $this->parent_id,
                'title' => $this->title,
                'route' => $this->route,
                'icon' => $this->icon,
                'permission' => $this->permission,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
            ],
        );

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $message);
    }

    public function delete(int $id): void
    {
        Menu::findOrFail($id)->delete();

        session()->flash('success', 'Menu deleted successfully.');
    }

    public function render()
    {
        $menus = Menu::query()
            ->with('parent')
            ->when($this->search, function ($query) {
                $query
                    ->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('route', 'like', '%' . $this->search . '%')
                    ->orWhere('permission', 'like', '%' . $this->search . '%');
            })
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->paginate(10);

        $parentMenus = Menu::all()->whereNull('parent_id')->sortBy('sort_order');
        $permissions = Permission::all()->sortBy('name');

        return view('livewire.menus.menu-table', [
            'menus' => $menus,
            'parentMenus' => $parentMenus,
            'permissions' => $permissions,
        ]);
    }
}
