<?php

namespace App\Services;

use App\Models\Company;
use App\Core\Services\BaseService;

class CompanyService extends BaseService
{
    public function create(array $data): Company
    {
        return $this->transaction(function () use ($data) {
            $this->guardSuperAdmin();

            return Company::create($data);
        });
    }

    public function update(Company $company, array $data): Company
    {
        return $this->transaction(function () use ($company, $data) {
            $this->guardSuperAdmin();

            $company->update($data);

            return $company;
        });
    }

    public function delete(Company $company): void
    {
        $this->transaction(function () use ($company) {
            $this->guardSuperAdmin();

            Company::query()->whereKey($company->id)->delete();
        });
    }
}
