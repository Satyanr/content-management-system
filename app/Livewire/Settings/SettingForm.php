<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Services\SettingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Traits\HasFlashMessage;

class SettingForm extends Component
{
    use HasFlashMessage;

    public string $app_name = '';
    public string $timezone = 'Asia/Jakarta';
    public bool $maintenance_mode = false;

    public ?int $companyId = null;

    public function mount(SettingService $settingService): void
    {
        $this->companyId = Auth::user()?->hasRole('super-admin') ? null : Auth::user()?->company_id;

        $this->app_name = $settingService->get(key: 'app_name', default: config('app.name', 'Digital Signage CMS'), companyId: $this->companyId);

        $this->timezone = $settingService->get(key: 'timezone', default: 'Asia/Jakarta', companyId: $this->companyId);

        $this->maintenance_mode = filter_var($settingService->get(key: 'maintenance_mode', default: false, companyId: null), FILTER_VALIDATE_BOOLEAN);
    }

    public function save(SettingService $settingService): void
    {
        Gate::authorize('settings.edit');

        $this->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'maintenance_mode' => ['boolean'],
        ]);

        $settingService->setMany(
            [
                [
                    'key' => 'app_name',
                    'value' => $this->app_name,
                    'group' => 'general',
                    'type' => 'text',
                    'is_public' => true,
                ],
                [
                    'key' => 'timezone',
                    'value' => $this->timezone,
                    'group' => 'general',
                    'type' => 'text',
                    'is_public' => true,
                ],
            ],
            $this->companyId,
        );

        $settingService->set(key: 'maintenance_mode', value: $this->maintenance_mode ? '1' : '0', companyId: null, group: 'system', type: 'boolean', isPublic: false);

        $this->success('Settings updated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.setting-form');
    }
}
