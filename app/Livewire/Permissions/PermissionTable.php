<?php

namespace App\Livewire\Permissions;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;

class PermissionTable extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEdit = false;

    public ?int $permissionId = null;
    public string $name = '';

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
        $this->reset(['permissionId', 'name', 'isEdit']);
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $permission = Permission::findOrFail($id);

        $this->permissionId = $permission->id;
        $this->name = $permission->name;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($this->permissionId)],
        ]);

        Permission::updateOrCreate(
            ['id' => $this->permissionId],
            [
                'name' => $this->name,
                'guard_name' => 'web',
            ],
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $this->isEdit ? 'Permission updated successfully.' : 'Permission created successfully.');
    }

    public function delete(int $id): void
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('success', 'Permission deleted successfully.');
    }

    public function render()
    {
        $permissions = Permission::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('livewire.permissions.permission-table', [
            'permissions' => $permissions,
        ]);
    }
}
