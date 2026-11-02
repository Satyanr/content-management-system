<?php

namespace App\Livewire\Playlists;

use App\Models\Device;
use App\Models\DeviceGroup;
use App\Services\DevicePlaylistAssignment;
use App\Services\PlaylistWorkspace;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PlaylistAssignment extends Component
{
    #[Locked]
    public int $playlistId;

    #[Locked]
    public int $companyId;

    public array $selected = [];

    public string $group = '';

    public function mount(int $playlistId): void
    {
        $this->playlistId = $playlistId;
        $this->companyId = app(PlaylistWorkspace::class)->find($playlistId)->company_id;
    }

    private function playlist()
    {
        Gate::authorize('devices.view');
        abort_unless(app(WorkspaceService::class)->companyId() === $this->companyId, 403);

        return app(PlaylistWorkspace::class)->find($this->playlistId);
    }

    public function selectAll(): void
    {
        $this->playlist();
        Gate::authorize('devices.assign');
        $this->selected = Device::forCompany($this->companyId)->pluck('id')->all();
    }

    public function apply(): void
    {
        $this->playlist();
        app(DevicePlaylistAssignment::class)->apply($this->playlistId, $this->selected);
        session()->flash('success', 'Perintah tersimpan. Perangkat offline menunggu sinkronisasi.');
    }

    public function render()
    {
        $playlist = $this->playlist();
        $devices = Device::forCompany($this->companyId)->when($this->group !== '', fn ($q) => $q->whereHas('groups', fn ($q) => $q->where('device_groups.company_id', $this->companyId)->where('device_groups.id', $this->group)))->orderBy('name')->limit(1000)->get();
        $assigned = Device::forCompany($this->companyId)->where(fn ($q) => $q->where('primary_playlist_id', $playlist->id)->orWhere('reported_playlist_id', $playlist->id))->orderBy('name')->get();

        return view('livewire.playlists.playlist-assignment', ['playlist' => $playlist, 'devices' => $devices, 'assigned' => $assigned, 'groups' => DeviceGroup::where('company_id', $this->companyId)->orderBy('name')->get()]);
    }
}
