<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WorkspaceService
{
    public const SESSION_KEY = 'workspace_company_id';

    public function companyId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if (! $user->hasRole('super-admin')) {
            return $user->company_id;
        }

        $companyId = Session::get(self::SESSION_KEY);

        return $companyId ? (int) $companyId : null;
    }

    public function company(): ?Company
    {
        $companyId = $this->companyId();

        if ($companyId === null) {
            return null;
        }

        return Company::query()
            ->where('id', '=', $companyId)
            ->first();
    }

    public function isAllCompanies(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (! $user->hasRole('super-admin')) {
            return false;
        }

        return Session::get(self::SESSION_KEY) === null;
    }

    public function setCompany(?int $companyId): void
    {
        if ($companyId === null) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        $companyExists = Company::query()
            ->where('id', '=', $companyId)
            ->exists();

        if (! $companyExists) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        Session::put(self::SESSION_KEY, $companyId);
    }
}