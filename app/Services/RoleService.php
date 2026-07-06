<?php

namespace App\Services;

use App\Core\Services\BaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService
{
    public function create(array $data): Role
    {
        return $this->transaction(function () use ($data) {
            $role = Role::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            $this->activityLog(
                action: 'created',
                module: 'roles',
                description: 'Created role ' . $role->name,
                subject: $role,
                newValues: [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $data['permissions'] ?? [],
                ]
            );

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return $this->transaction(function () use ($role, $data) {
            $role->load('permissions');

            $oldValues = [
                'name' => $role->name,
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->values()
                    ->toArray(),
            ];

            $role->update([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            $role->load('permissions');

            $this->activityLog(
                action: 'updated',
                module: 'roles',
                description: 'Updated role ' . $role->name,
                subject: $role,
                oldValues: $oldValues,
                newValues: [
                    'name' => $role->name,
                    'permissions' => $role->permissions
                        ->pluck('name')
                        ->values()
                        ->toArray(),
                ]
            );

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->transaction(function () use ($role) {
            if ($role->name === 'super-admin') {
                throw new AuthorizationException('Super admin role cannot be deleted.');
            }

            $role->load('permissions');

            $oldValues = [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->values()
                    ->toArray(),
            ];

            $this->activityLog(
                action: 'deleted',
                module: 'roles',
                description: 'Deleted role ' . $role->name,
                subject: $role,
                oldValues: $oldValues
            );

            Role::query()
                ->whereKey($role->id)
                ->delete();
        });
    }
}