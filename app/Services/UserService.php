<?php

namespace App\Services;

use App\Models\User;
use App\Core\Services\BaseService;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function create(array $data): User
    {
        return $this->transaction(function () use ($data) {
            $companyId = $this->resolveCompanyId($data['company_id'] ?? null);

            $this->guardCompanyAccess($companyId);

            $user = User::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole($data['role']);

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return $this->transaction(function () use ($user, $data) {
            $this->guardCompanyAccess($user->company_id);

            $companyId = $this->resolveCompanyId($data['company_id'] ?? $user->company_id);

            $this->guardCompanyAccess($companyId);

            $user->company_id = $companyId;
            $user->name = $data['name'];
            $user->email = $data['email'];

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            return $user;
        });
    }

    public function delete(User $user): void
    {
        $this->transaction(function () use ($user) {
            $this->guardCompanyAccess($user->company_id);

            User::query()->whereKey($user->id)->delete();
        });
    }
}
