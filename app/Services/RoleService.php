<?php

namespace App\Services;

use App\Core\Services\BaseService;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService
{
    public function create(array $data): Role
    {
        return $this->transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return $this->transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->transaction(function () use ($role) {
            Role::query()
                ->whereKey($role->id)
                ->delete();
        });
    }
}