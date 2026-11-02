<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceManager
{
    public function save(int $company, ?int $id, array $input, int $creator): Device
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? '');
        $data = validator($input, [
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:5000'], 'platform' => ['required', 'string', Rule::in(['windows', 'android', 'linux', 'other'])],
            'location' => ['nullable', 'string', 'max:200'], 'screen_width' => ['nullable', 'required_with:screen_height', 'integer', 'between:1,16384'], 'screen_height' => ['nullable', 'required_with:screen_width', 'integer', 'between:1,16384'],
            'orientation' => ['required', 'string', Rule::in(['landscape', 'portrait'])], 'groups' => ['present', 'array', 'list', 'max:100'], 'groups.*' => ['integer', 'distinct'],
        ])->validate();

        return DB::transaction(function () use ($company, $id, $data, $creator) {
            $device = $id ? Device::forCompany($company)->lockForUpdate()->findOrFail($id) : Device::create(array_diff_key($data, ['groups' => true]) + ['company_id' => $company, 'created_by' => $creator]);
            if ($id) {
                $device->update(array_diff_key($data, ['groups' => true]));
            }
            $ids = array_map('intval', $data['groups']);
            if (DeviceGroup::forCompany($company)->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['groups' => 'Choose only Groups in this company.']);
            }
            $device->groups()->sync(array_fill_keys($ids, ['company_id' => $company]));
            app(DeviceLifecycle::class)->audit($device, $id ? 'updated' : 'created', ['public_id' => $device->public_id]);

            return $device;
        });
    }

    public function membership(int $company, int $deviceId, int $groupId, bool $add): void
    {
        DB::transaction(function () use ($company, $deviceId, $groupId, $add) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($deviceId);
            $group = DeviceGroup::forCompany($company)->findOrFail($groupId);
            if ($add) {
                $device->groups()->syncWithoutDetaching([$group->id => ['company_id' => $company]]);
            } else {
                $device->groups()->detach($group->id);
            }
            app(DeviceLifecycle::class)->audit($device, $add ? 'group.added' : 'group.removed', ['group_id' => $group->id]);
        });
    }
}
