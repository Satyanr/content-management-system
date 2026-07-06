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

            $company = Company::query()->create($data);

            $this->activityLog(
                action: 'created',
                module: 'companies',
                description: 'Created company ' . $company->name,
                subject: $company,
                newValues: [
                    'id' => $company->id,
                    'name' => $company->name,
                    'code' => $company->code,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'is_active' => $company->is_active,
                ],
            );

            return $company;
        });
    }

    public function update(Company $company, array $data): Company
    {
        return $this->transaction(function () use ($company, $data) {
            $this->guardSuperAdmin();

            $oldValues = [
                'name' => $company->name,
                'code' => $company->code,
                'email' => $company->email,
                'phone' => $company->phone,
                'is_active' => $company->is_active,
            ];

            $company->update($data);

            $this->activityLog(
                action: 'updated',
                module: 'companies',
                description: 'Updated company ' . $company->name,
                subject: $company,
                oldValues: $oldValues,
                newValues: [
                    'name' => $company->name,
                    'code' => $company->code,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'is_active' => $company->is_active,
                ],
            );

            return $company;
        });
    }

    public function delete(Company $company): void
    {
        $this->transaction(function () use ($company) {
            $this->guardSuperAdmin();

            $oldValues = [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'email' => $company->email,
                'phone' => $company->phone,
                'is_active' => $company->is_active,
            ];

            $this->activityLog(action: 'deleted', module: 'companies', description: 'Deleted company ' . $company->name, subject: $company, oldValues: $oldValues);

            Company::query()->whereKey($company->id)->delete();
        });
    }
}
