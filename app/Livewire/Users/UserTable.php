<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Services\UserService;
use App\Livewire\Traits\HasModal;
use App\Livewire\Traits\HasFlashMessage;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use App\Services\WorkspaceService;

class UserTable extends Component
{
    use WithPagination;
    use HasModal;
    use HasFlashMessage;

    public string $search = '';

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';
    public ?int $userId = null;
    public ?int $company_id = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetForm();

        $workspaceCompanyId = app(\App\Services\WorkspaceService::class)->companyId();

        $this->company_id = $workspaceCompanyId ?? Auth::user()?->company_id;

        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->reset(['userId', 'name', 'email', 'company_id', 'password', 'role', 'isEdit']);
        $this->resetValidation();
    }

    public function save(UserService $userService): void
    {
        Gate::authorize($this->isEdit ? 'users.edit' : 'users.create');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $this->userId],
            'role' => ['required', 'exists:roles,name'],
            'company_id' => ['required', 'exists:companies,id'],
        ];

        if (!$this->isEdit) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } elseif ($this->password) {
            $rules['password'] = ['string', 'min:8'];
        }

        $this->validate($rules);

        if (!Auth::user()?->hasRole('super-admin') && $this->role === 'super-admin') {
            abort(403);
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'company_id' => $this->company_id,
            'password' => $this->password,
            'role' => $this->role,
        ];

        $message = $this->isEdit ? 'User updated successfully.' : 'User created successfully.';

        if ($this->isEdit) {
            $user = User::query()->findOrFail($this->userId);
            $userService->update($user, $data);
        } else {
            $userService->create($data);
        }

        $this->closeModal();
        $this->resetForm();

        $this->success($message);
    }

    public function edit(int $id): void
    {
        Gate::authorize('users.edit');

        $user = User::query()->with('roles')->findOrFail($id);

        if (!Auth::user()?->hasRole('super-admin') && $user->company_id !== Auth::user()?->company_id) {
            abort(403);
        }

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->company_id = $user->company_id;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? '';
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function delete(int $id, UserService $userService): void
    {
        Gate::authorize('users.delete');
        $user = User::query()->findOrFail($id);

        if ($user->id === Auth::id()) {
            $this->error('You cannot delete your own account.');
            return;
        }

        if (!Auth::user()?->hasRole('super-admin') && $user->company_id !== Auth::user()?->company_id) {
            abort(403);
        }

        $userService->delete($user);

        $this->success('User deleted successfully.');
    }

    public function render()
    {
        $workspaceCompanyId = app(WorkspaceService::class)->companyId();

        $users = User::query()
            ->with(['roles', 'company'])
            ->when($workspaceCompanyId !== null, function ($query) use ($workspaceCompanyId) {
                $query->where('company_id', '=', $workspaceCompanyId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $roles = Role::query()
            ->when(!Auth::user()?->hasRole('super-admin'), function ($query) {
                $query->where('name', '!=', 'super-admin');
            })
            ->orderBy('name', 'asc')
            ->get();
        $companies = Company::query()
            ->when($workspaceCompanyId !== null, function ($query) use ($workspaceCompanyId) {
                $query->where('id', '=', $workspaceCompanyId);
            })
            ->orderBy('name', 'asc')
            ->get();

        return view('livewire.users.user-table', [
            'users' => $users,
            'roles' => $roles,
            'companies' => $companies,
        ]);
    }
}
