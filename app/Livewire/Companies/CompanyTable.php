<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use App\Services\CompanyService;
use App\Livewire\Traits\HasModal;
use App\Livewire\Traits\HasFlashMessage;

class CompanyTable extends Component
{
    use WithPagination;
    use HasModal;
    use HasFlashMessage;

    public string $search = '';

    public ?int $companyId = null;
    public string $name = '';
    public string $code = '';
    public ?string $email = null;
    public ?string $phone = null;
    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset([
            'companyId',
            'name',
            'code',
            'email',
            'phone',
            'is_active',
            'isEdit',
        ]);

        $this->is_active = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $company = Company::query()->findOrFail($id);

        $this->companyId = $company->id;
        $this->name = $company->name;
        $this->code = $company->code;
        $this->email = $company->email;
        $this->phone = $company->phone;
        $this->is_active = $company->is_active;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(CompanyService $companyService): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('companies', 'code')->ignore($this->companyId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $data = [
            'name' => $this->name,
            'code' => strtoupper($this->code),
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
        ];

        $message = $this->isEdit
            ? 'Company updated successfully.'
            : 'Company created successfully.';

        if ($this->isEdit) {
            $company = Company::query()->findOrFail($this->companyId);
            $companyService->update($company, $data);
        } else {
            $companyService->create($data);
        }

        $this->closeModal();
        $this->resetForm();

        $this->success($message);
    }

    public function delete(int $id, CompanyService $companyService): void
    {
        $company = Company::query()->findOrFail($id);

        if ($company->users()->exists()) {
            $this->error('Company cannot be deleted because it has users.');
            return;
        }

        $companyService->delete($company);

        $this->success('Company deleted successfully.');
    }

    public function render()
    {
        $companies = Company::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.companies.company-table', [
            'companies' => $companies,
        ]);
    }
}