<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DevicePlaylistAssignment
{
    public function apply(int $playlistId, array $deviceIds, string $source = 'playlist'): string
    {
        Gate::authorize('devices.assign');
        Gate::authorize('devices.view');
        $playlist = app(PlaylistWorkspace::class)->find($playlistId);
        abort_unless($playlist->is_active, 422, 'Pilih Playlist aktif.');
        $valid = validator(['ids' => $deviceIds], ['ids' => 'required|array|list|min:1|max:1000', 'ids.*' => 'required|integer|distinct'])->validate();

        return DB::transaction(function () use ($playlist, $valid, $source) {
            $devices = Device::forCompany($playlist->company_id)->whereIn('id', $valid['ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($devices->count() === count($valid['ids']), 404);
            $command = (string) Str::uuid();
            foreach ($devices as $device) {
                $device->forceFill(['primary_playlist_id' => $playlist->id, 'playlist_command_id' => $command, 'playlist_command_at' => now()])->save();
                DB::table('device_playlist_changes')->insert(['company_id' => $device->company_id, 'device_id' => $device->id, 'playlist_id' => $playlist->id, 'user_id' => auth()->id(), 'source' => $source, 'command_id' => $command, 'created_at' => now()]);
            }

            return $command;
        });
    }
}
