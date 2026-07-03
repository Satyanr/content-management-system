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
            $permission = Permission::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $permission;
        });
    }

    public function update(Permission $permission, array $data): Permission
    {
        return $this->transaction(function () use ($permission, $data) {
            $permission->update([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $permission;
        });
    }

    public function delete(Permission $permission): void
    {
        $this->transaction(function () use ($permission) {
            Permission::query()
                ->whereKey($permission->id)
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}