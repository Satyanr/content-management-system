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
            $user = User::create([
                'company_id' => $data['company_id'],
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
            $user->company_id = $data['company_id'];
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
            User::query()->whereKey($user->id)->delete();
        });
    }
}
