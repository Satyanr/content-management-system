<?php

namespace App\Livewire\Devices;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Playlist;
use App\Services\DevicePlaylistAssignment;
use App\Services\DeviceWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public string $primaryPlaylist = '';

    private function assignedDevice(): Device
    {
        abort_unless(app(DeviceWorkspace::class)->company() === $this->workspaceId, 403);

        return Device::forCompany($this->workspaceId)->where('public_id', $this->publicId)->firstOrFail();
    }

    public function applyPlaylist(): void
    {
        $device = $this->assignedDevice();
        $this->validate(['primaryPlaylist' => 'required|integer|min:1']);
        app(DevicePlaylistAssignment::class)->apply((int) $this->primaryPlaylist, [$device->id], 'device');
        session()->flash('success', 'Perintah tersimpan; menunggu konfirmasi Player.');
    }

    public function retainPlaylist(): void
    {
        $device = $this->assignedDevice();
        abort_unless($device->reported_playlist_id, 422);
        app(DevicePlaylistAssignment::class)->apply($device->reported_playlist_id, [$device->id], 'retain');
    }

    public function render()
    {
        $company = app(DeviceWorkspace::class)->company();
        abort_unless($company === $this->workspaceId, 403, 'Workspace changed. Reload Device Details.');
        $device = Device::forCompany($company)->where('public_id', $this->publicId)->with(['groups', 'primaryPlaylist' => fn ($q) => $q->where('company_id', $company)])->withCount('activeCredentials')->firstOrFail();
        $timezone = app(DeviceWorkspace::class)->timezone($company);
        $pairings = $device->pairingCodes()->latest('id')->limit(10)->get(['id', 'device_id', 'created_at', 'expires_at', 'consumed_at', 'cancelled_at', 'attempts']);
        $credentials = $device->credentials()->latest('id')->limit(10)->get(['id', 'device_id', 'created_at', 'revoked_at', 'last_used_at']);
        $events = ActivityLog::where('company_id', $company)->where('module', 'devices')->where('subject_type', Device::class)->where('subject_id', $device->id)->latest('id')->limit(20)->get(['id', 'action', 'created_at']);

        $playlists = Gate::allows('playlists.view') ? Playlist::forCompany($company)->where('is_active', true)->orderBy('name')->get() : collect();
        $reportedPlaylist = Playlist::forCompany($company)->find($device->reported_playlist_id);

        $playlistChanges = DB::table('device_playlist_changes')->where('company_id', $company)->where('device_id', $device->id)->orderByDesc('id')->limit(10)->get();

        return view('livewire.devices.device-details', compact('device', 'timezone', 'pairings', 'credentials', 'events', 'playlists', 'reportedPlaylist', 'playlistChanges'));
    }
}
