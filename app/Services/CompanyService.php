<?php

namespace App\Services;

use App\Models\Company;
use App\Core\Services\BaseService;

class CompanyService extends BaseService
{
    public function create(array $data): Company
    {
        return $this->transaction(function () use ($data) {
            return Company::create($data);
        });
    }

    public function update(Company $company, array $data): Company
    {
        return $this->transaction(function () use ($company, $data) {
            $company->update($data);

            return $company;
        });
    }

    public function delete(Company $company): void
    {
        $this->transaction(function () use ($company) {
            Company::query()
                ->whereKey($company->id)
                ->delete();
        });
    }
}