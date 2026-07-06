<?php

namespace App\Services;

use App\Core\Services\BaseService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService extends BaseService
{
    public function create(array $data): Permission
    {
        return $this->transaction(function () use ($data) {
            $permission = Permission::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->activityLog(
                action: 'created',
                module: 'permissions',
                description: 'Created permission ' . $permission->name,
                subject: $permission,
                newValues: [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ],
            );

            return $permission;
        });
    }

    public function update(Permission $permission, array $data): Permission
    {
        return $this->transaction(function () use ($permission, $data) {
            $oldValues = [
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ];

            $permission->update([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->activityLog(
                action: 'updated',
                module: 'permissions',
                description: 'Updated permission ' . $permission->name,
                subject: $permission,
                oldValues: $oldValues,
                newValues: [
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ],
            );

            return $permission;
        });
    }

    public function delete(Permission $permission): void
    {
        $this->transaction(function () use ($permission) {
            $oldValues = [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ];

            $this->activityLog(action: 'deleted', module: 'permissions', description: 'Deleted permission ' . $permission->name, subject: $permission, oldValues: $oldValues);

            Permission::query()->whereKey($permission->id)->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
