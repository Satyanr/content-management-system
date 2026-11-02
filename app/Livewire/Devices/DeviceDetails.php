<?php

namespace App\Livewire\Devices;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Services\DeviceWorkspace;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DeviceDetails extends Component
{
    #[Locked]
    public string $publicId;

    #[Locked]
    public int $workspaceId;

    public function mount(string $publicId): void
    {
        $this->workspaceId = app(DeviceWorkspace::class)->company();
        $this->publicId = $publicId;
    }

    public function render()
    {
        $company = app(DeviceWorkspace::class)->company();
        abort_unless($company === $this->workspaceId, 403, 'Workspace changed. Reload Device Details.');
        $device = Device::forCompany($company)->where('public_id', $this->publicId)->with('groups')->withCount('activeCredentials')->firstOrFail();
        $timezone = app(DeviceWorkspace::class)->timezone($company);
        $pairings = $device->pairingCodes()->latest('id')->limit(10)->get(['id', 'device_id', 'created_at', 'expires_at', 'consumed_at', 'cancelled_at', 'attempts']);
        $credentials = $device->credentials()->latest('id')->limit(10)->get(['id', 'device_id', 'created_at', 'revoked_at', 'last_used_at']);
        $events = ActivityLog::where('company_id', $company)->where('module', 'devices')->where('subject_type', Device::class)->where('subject_id', $device->id)->latest('id')->limit(20)->get(['id', 'action', 'created_at']);

        return view('livewire.devices.device-details', compact('device', 'timezone', 'pairings', 'credentials', 'events'));
    }
}
