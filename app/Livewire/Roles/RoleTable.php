<?php

namespace App\Livewire\Roles;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use App\Services\RoleService;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('roles.edit');

        $role = Role::with('permissions')->findOrFail($id);

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(RoleService $roleService): void
    {
        Gate::authorize($this->isEdit ? 'roles.edit' : 'roles.create');

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId)],
        ]);

        $data = [
            'name' => $this->name,
            'permissions' => $this->selectedPermissions,
        ];

        $message = $this->isEdit ? 'Role updated successfully.' : 'Role created successfully.';

        if ($this->isEdit) {
            $role = Role::findOrFail($this->roleId);
            $roleService->update($role, $data);
        } else {
            $roleService->create($data);
        }

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $message);
    }

    public function delete(int $id, RoleService $roleService): void
    {
        Gate::authorize('roles.delete');

        $role = Role::query()->findOrFail($id);

        if ($role->name === 'super-admin') {
            session()->flash('error', 'Super admin role cannot be deleted.');
            return;
        }

        $roleService->delete($role);

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
