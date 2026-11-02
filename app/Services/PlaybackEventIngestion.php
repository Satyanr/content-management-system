<?php

namespace App\Services;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlaybackEventIngestion
{
    public function ingest(Device $device, array $input): array
    {
        $valid = validator($input, [
            'company_id' => 'prohibited', 'device_id' => 'prohibited', 'events' => 'required|array|list|min:1|max:50',
            'events.*' => 'array:event_id,playback_session_id,revision,playlist_id,page_id,zone_id,playlist_zone_item_id,media_asset_id,schedule_id,start_time_utc,end_time_utc,actual_played_ms,expected_duration_ms,status,reason,counter_epoch',
            'events.*.event_id' => 'required|uuid', 'events.*.playback_session_id' => 'required|uuid', 'events.*.revision' => 'required|regex:/^[a-f0-9]{64}$/D',
            'events.*.playlist_id' => 'required|integer|min:1', 'events.*.page_id' => 'required|integer|min:1', 'events.*.zone_id' => 'required|integer|min:1', 'events.*.playlist_zone_item_id' => 'required|integer|min:1', 'events.*.media_asset_id' => 'required|integer|min:1', 'events.*.schedule_id' => 'present|nullable|integer|min:1',
            'events.*.start_time_utc' => 'required|date|regex:/Z$/D', 'events.*.end_time_utc' => 'required|date|regex:/Z$/D',
            'events.*.actual_played_ms' => 'required|integer|between:0,86402000', 'events.*.expected_duration_ms' => 'present|nullable|integer|between:1,86400000',
            'events.*.status' => 'required|in:completed,interrupted,failed', 'events.*.reason' => 'required|regex:/^[a-z_]{1,80}$/D', 'events.*.counter_epoch' => 'required|uuid',
        ])->validate();

        return DB::transaction(function () use ($device, $valid) {
            Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $ack = [];
            foreach ($valid['events'] as $event) {
                ksort($event);
                $hash = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
                $existing = DB::table('playback_events')->where('device_id', $device->id)->where('event_id', $event['event_id'])->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->payload_hash, $hash), 409);
                    $ack[] = $event['event_id'];

                    continue;
                }
                abort_if(DB::table('playback_events')->where('device_id', $device->id)->where('playback_session_id', $event['playback_session_id'])->exists(), 409);
                $package = DB::table('device_playback_packages')->where('company_id', $device->company_id)->where('device_id', $device->id)->where('revision', $event['revision'])->first();
                abort_unless($package, 422);
                $grants = json_decode($package->grants, true);
                $grant = $grants['items'][(string) $event['playlist_zone_item_id']] ?? null;
                abort_unless($grant, 422);
                foreach (['playlist_id', 'page_id', 'zone_id', 'playlist_zone_item_id', 'media_asset_id', 'counter_epoch'] as $key) {
                    abort_unless((string) $grant[$key] === (string) $event[$key], 422);
                }
                abort_if($event['schedule_id'] !== null && ! in_array($event['schedule_id'], $grants['scheduleIds'] ?? [], true), 422);
                $start = CarbonImmutable::parse($event['start_time_utc'])->utc();
                $end = CarbonImmutable::parse($event['end_time_utc'])->utc();
                $wall = (int) round($start->diffInMilliseconds($end, false));
                abort_unless($end->gte($start) && $end->lte(CarbonImmutable::now('UTC')->addMinutes(5)) && $start->gte(CarbonImmutable::parse($package->created_at, 'UTC')->subMinutes(5)) && $wall + 1000 >= $event['actual_played_ms'], 422);
                $expected = $grant['duration'] === null ? $event['expected_duration_ms'] : (int) round($grant['duration'] * 1000);
                abort_unless($expected === $event['expected_duration_ms'] && ($expected === null || $event['actual_played_ms'] <= $expected + 2000) && ($event['status'] !== 'completed' || ($expected !== null && $event['actual_played_ms'] >= $expected)), 422);
                $row = $event;
                $row['start_time_utc'] = $start->format('Y-m-d H:i:s.v');
                $row['end_time_utc'] = $end->format('Y-m-d H:i:s.v');
                DB::table('playback_events')->insert($row + ['company_id' => $device->company_id, 'device_id' => $device->id, 'names' => json_encode($grant['names'], JSON_THROW_ON_ERROR), 'payload_hash' => $hash, 'received_at' => now()]);
                if ($event['status'] === 'completed') {
                    DB::table('device_item_counters')->where('company_id', $device->company_id)->where('device_id', $device->id)->where('playlist_zone_item_id', $event['playlist_zone_item_id'])->where('counter_epoch', $event['counter_epoch'])->increment('completed_count');
                }
                $ack[] = $event['event_id'];
            }
            $device->forceFill(['events_received_at' => now()])->save();

            return ['acknowledged' => $ack, 'serverTimeUtc' => CarbonImmutable::now('UTC')->toIso8601String()];
        });
    }

    public function state(Device $device, array $input): array
    {
        $valid = validator($input, ['company_id' => 'prohibited', 'device_id' => 'prohibited', 'revision' => 'required|regex:/^[a-f0-9]{64}$/D', 'playlist_id' => 'present|nullable|integer|min:1', 'command_id' => 'present|nullable|string|max:64', 'pending_events' => 'required|integer|between:0,100000'])->validate();
        $package = DB::table('device_playback_packages')->where('company_id', $device->company_id)->where('device_id', $device->id)->where('revision', $valid['revision'])->first();
        abort_unless($package, 422);
        $grants = json_decode($package->grants, true);
        abort_unless(collect($grants['plan'] ?? [])->contains(fn ($e) => $e['playlistId'] === $valid['playlist_id'] && $e['commandId'] === $valid['command_id']), 422);
        DB::transaction(function () use ($device, $valid) {
            $locked = Device::lockForUpdate()->findOrFail($device->id);
            if ($locked->reported_command_id !== $valid['command_id'] || $locked->reported_playlist_id !== $valid['playlist_id']) {
                DB::table('device_playlist_changes')->insert(['company_id' => $device->company_id, 'device_id' => $device->id, 'playlist_id' => $valid['playlist_id'], 'user_id' => null, 'source' => 'player_report', 'manifest_revision' => $valid['revision'], 'command_id' => $valid['command_id'] ?? 'none', 'created_at' => now()]);
            }
            $locked->forceFill(['reported_playlist_id' => $valid['playlist_id'], 'reported_command_id' => $valid['command_id'], 'reported_revision' => $valid['revision'], 'playback_reported_at' => now(), 'pending_playback_events' => $valid['pending_events']])->save();
        });

        return ['accepted' => true];
    }
}
