<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserTable extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';
    public ?int $userId = null;
    public bool $isEdit = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function resetForm(): void
    {
        $this->reset(['userId', 'name', 'email', 'password', 'role', 'isEdit']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $this->userId],
            'role' => ['required', 'exists:roles,name'],
        ];

        if (!$this->isEdit) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } elseif ($this->password) {
            $rules['password'] = ['string', 'min:8'];
        }

        $this->validate($rules);

        $user = $this->isEdit ? User::findOrFail($this->userId) : new User();

        $user->name = $this->name;
        $user->email = $this->email;

        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        $user->save();
        $user->syncRoles([$this->role]);

        $this->closeModal();
        $this->resetForm();

        session()->flash('success', $this->isEdit ? 'User updated successfully.' : 'User created successfully.');
    }

    public function edit(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? '';
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user->delete();

        session()->flash('success', 'User deleted successfully.');
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.users.user-table', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
