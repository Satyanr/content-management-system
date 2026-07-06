<?php

namespace App\Services;

use App\Models\Setting;
use App\Core\Services\BaseService;

class SettingService extends BaseService
{
    public function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        if ($companyId !== null) {
            $companySetting = Setting::query()->where('key', '=', $key)->where('company_id', '=', $companyId)->first();

            if ($companySetting) {
                return $companySetting->value;
            }
        }

        $globalSetting = Setting::query()->where('key', '=', $key)->where('company_id', '=', null)->first();

        return $globalSetting?->value ?? $default;
    }

    public function set(string $key, mixed $value, ?int $companyId = null, string $group = 'general', string $type = 'text', bool $isPublic = false): Setting
    {
        return $this->transaction(function () use ($key, $value, $companyId, $group, $type, $isPublic) {
            $oldSetting = Setting::query()->where('company_id', '=', $companyId)->where('key', '=', $key)->first();

            $oldValues = $oldSetting
                ? [
                    'company_id' => $oldSetting->company_id,
                    'group' => $oldSetting->group,
                    'key' => $oldSetting->key,
                    'value' => $oldSetting->value,
                    'type' => $oldSetting->type,
                    'is_public' => $oldSetting->is_public,
                ]
                : null;

            $setting = Setting::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [
                    'group' => $group,
                    'value' => $value,
                    'type' => $type,
                    'is_public' => $isPublic,
                ],
            );

            $action = $oldSetting ? 'updated' : 'created';

            $scope = $companyId ? 'company' : 'global';

            $this->activityLog(
                action: $action,
                module: 'settings',
                description: ucfirst($action) . ' ' . $scope . ' setting ' . $key,
                subject: $setting,
                oldValues: $oldValues,
                newValues: [
                    'company_id' => $setting->company_id,
                    'group' => $setting->group,
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'is_public' => $setting->is_public,
                ],
            );

            return $setting;
        });
    }

    public function setMany(array $settings, ?int $companyId = null): void
    {
        foreach ($settings as $setting) {
            $this->set(key: $setting['key'], value: $setting['value'], companyId: $companyId, group: $setting['group'] ?? 'general', type: $setting['type'] ?? 'text', isPublic: $setting['is_public'] ?? false);
        }
    }
}
