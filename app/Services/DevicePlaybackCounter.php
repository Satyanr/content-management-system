<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\PlaylistZoneItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DevicePlaybackCounter
{
    public function get(Device $device, int $item): array
    {
        DB::table('device_item_counters')->insertOrIgnore(['company_id' => $device->company_id, 'device_id' => $device->id, 'playlist_zone_item_id' => $item, 'counter_epoch' => (string) Str::uuid(), 'completed_count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return (array) DB::table('device_item_counters')->where('company_id', $device->company_id)->where('device_id', $device->id)->where('playlist_zone_item_id', $item)->first();
    }

    public function reset(int $deviceId, int $itemId): void
    {
        Gate::authorize('playback.reset');
        Gate::authorize('devices.view');
        Gate::authorize('playlists.view');
        $company = app(DeviceWorkspace::class)->company();
        $device = Device::forCompany($company)->findOrFail($deviceId);
        PlaylistZoneItem::whereHas('zone.page.playlist', fn ($q) => $q->where('company_id', $company))->findOrFail($itemId);
        DB::transaction(function () use ($device, $itemId) {
            $old = $this->get($device, $itemId);
            $epoch = (string) Str::uuid();
            DB::table('device_item_counters')->where('device_id', $device->id)->where('playlist_zone_item_id', $itemId)->update(['counter_epoch' => $epoch, 'completed_count' => 0, 'updated_at' => now()]);
            ActivityLog::create(['company_id' => $device->company_id, 'user_id' => auth()->id(), 'module' => 'playback', 'action' => 'reset_counter', 'description' => 'Reset counter perangkat '.$device->id.' item '.$itemId, 'old_values' => ['counter_epoch' => $old['counter_epoch'], 'completed_count' => $old['completed_count']], 'new_values' => ['counter_epoch' => $epoch, 'completed_count' => 0]]);
        });
    }
}
