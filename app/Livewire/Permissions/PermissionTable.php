<?php

namespace App\Livewire\Permissions;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('permissions.edit');

        $permission = Permission::query()->findOrFail($id);

        $this->permissionId = $permission->id;
        $this->name = $permission->name;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(PermissionService $permissionService): void
    {
        Gate::authorize($this->isEdit ? 'permissions.edit' : 'permissions.create');

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($this->permissionId)],
        ]);

        $data = [
            'name' => $this->name,
        ];

        $message = $this->isEdit ? 'Permission updated successfully.' : 'Permission created successfully.';

        if ($this->isEdit) {
            $permission = Permission::findOrFail($this->permissionId);
            $permissionService->update($permission, $data);
        } else {
            $permissionService->create($data);
        }

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $message);
    }

    public function delete(int $id, PermissionService $permissionService): void
    {
        Gate::authorize('permissions.delete');

        $permission = Permission::query()->findOrFail($id);

        $permissionService->delete($permission);

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
