<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;

class DeviceWorkspace
{
    public function company(bool $required = true): ?int
    {
        Gate::authorize('devices.view');
        $workspace = app(WorkspaceService::class);
        $id = $workspace->companyId();
        if ($id !== null) {
            abort_unless($workspace->company()?->is_active, 403, 'Workspace is unavailable.');
        }
        if ($required) {
            abort_if($id === null, 403, 'Select a company workspace first.');
        }

        return $id;
    }

    public function timezone(int $company): string
    {
        $tz = app(SettingService::class)->get('timezone', 'Asia/Jakarta', $company);

        return in_array($tz, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? $tz : 'Asia/Jakarta';
    }
}
