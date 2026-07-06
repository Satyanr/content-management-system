<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

abstract class BaseService
{
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    protected function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole('super-admin') ?? false;
    }

    protected function guardSuperAdmin(): void
    {
        if (!$this->isSuperAdmin()) {
            throw new AuthorizationException('Only super admin can perform this action.');
        }
    }

    protected function currentCompanyId(): ?int
    {
        return Auth::user()?->company_id;
    }

    protected function guardCompanyAccess(?int $companyId): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        if ((int) $companyId !== (int) $this->currentCompanyId()) {
            throw new AuthorizationException('You are not allowed to access this company data.');
        }
    }

    protected function resolveCompanyId(?int $companyId): ?int
    {
        if ($this->isSuperAdmin()) {
            return $companyId;
        }

        return $this->currentCompanyId();
    }

    protected function activityLog(string $action, string $module, ?string $description = null, ?\Illuminate\Database\Eloquent\Model $subject = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        app(\App\Services\ActivityLogService::class)->log(action: $action, module: $module, description: $description, subject: $subject, oldValues: $oldValues, newValues: $newValues);
    }
}
