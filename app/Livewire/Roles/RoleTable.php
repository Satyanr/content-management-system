<?php

namespace App\Livewire\Roles;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;

class RoleTable extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEdit = false;

    public ?int $roleId = null;
    public string $name = '';

    public array $selectedPermissions = [];

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
        $this->reset(['roleId', 'name', 'isEdit', 'selectedPermissions']);
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId)],
        ]);

        $role = Role::updateOrCreate(
            ['id' => $this->roleId],
            [
                'name' => $this->name,
                'guard_name' => 'web',
            ],
        );

        $role->syncPermissions($this->selectedPermissions);

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $this->isEdit ? 'Role updated successfully.' : 'Role created successfully.');
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin') {
            session()->flash('error', 'Super admin role cannot be deleted.');
            return;
        }

        $role->delete();

        session()->flash('success', 'Role deleted successfully.');
    }

    public function render()
    {
        $roles = Role::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('livewire.roles.role-table', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }
}
