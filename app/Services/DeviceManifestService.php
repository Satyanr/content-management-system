<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Playlist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeviceManifestService
{
    public function build(Device $device, int $version = 1): array
    {
        return DB::transaction(fn () => $this->snapshot($device, $version));
    }

    private function snapshot(Device $device, int $version): array
    {
        // Stable UTC hour buckets avoid a new revision on every polling second.
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $from = $now->startOfHour();
        $until = $from->addHours(max(1, min(168, (int) config('player.horizon_hours', 72))));
        $resolver = app(ScheduleResolver::class);
        $segments = $resolver->timeline($device->company_id, $from, $until);
        $current = collect($segments)->first(fn ($segment) => $segment['start']->lte($now) && $segment['end']->gt($now));
        $schedule = $current['schedule'] ?? null;
        $scheduleData = fn ($s) => $s ? ['id' => $s->id, 'playlistId' => $s->playlist_id, 'name' => $s->name, 'priority' => $s->priority,
            'type' => $s->schedule_type, 'timezone' => $s->timezone, 'startsAtUtc' => $s->starts_at_utc?->toIso8601String(),
            'endsAtUtc' => $s->ends_at_utc?->toIso8601String(), 'startDate' => $s->start_date, 'endDate' => $s->end_date,
            'startTime' => $s->start_time, 'endTime' => $s->end_time, 'weekdays' => $s->weekdays] : null;
        $plan = array_map(fn ($segment) => ['startUtc' => $segment['start']->toIso8601String(), 'endUtc' => $segment['end']->toIso8601String(),
            'state' => $segment['schedule'] ? 'scheduled' : 'no_schedule', 'schedule' => $scheduleData($segment['schedule'])], $segments);
        $primary = null;
        $devicePlan = null;
        if ($version === 2) {
            $devicePlan = $resolver->devicePlan($device, $from, $until);
            $plan = $devicePlan['segments'];
            $currentPlan = collect($plan)->first(fn ($entry) => CarbonImmutable::parse($entry['startUtc'])->lte($now) && CarbonImmutable::parse($entry['endUtc'])->gt($now));
        }
        $ids = ($version === 2 ? collect($plan)->pluck('playlistId')->push($devicePlan['fallbackPlaylistId']) : collect($segments)->pluck('schedule.playlist_id'))->filter()->unique()->values();
        $playlists = Playlist::forCompany($device->company_id)->where('is_active', true)->whereIn('id', $ids)->orderBy('id')->with([
            'pages.zones.items.mediaAsset' => fn ($q) => $q->where('company_id', $device->company_id)->where('is_active', true)->whereIn('type', ['image', 'video']),
        ])->get();
        abort_unless($playlists->count() === $ids->count(), 409);
        $assets = $playlists->flatMap(fn ($p) => $p->pages->flatMap(fn ($page) => $page->zones->flatMap(fn ($zone) => $zone->items->pluck('mediaAsset'))))
            ->filter()->unique('id')->sortBy('id')->values();
        $media = $assets->map(fn ($asset) => app(DeviceMediaFile::class)->metadata($asset))->keyBy('id');
        $layouts = $playlists->map(fn ($playlist) => app(PlaylistLayout::class)->serialize($playlist, function ($item, $zone) use ($media, $version, $device) {
            $asset = $item->mediaAsset;
            $file = $asset ? $media->get($asset->id) : null;
            $known = $asset?->duration ?: ($asset?->metadata['duration'] ?? null);
            $known = is_numeric($known) && is_finite((float) $known) && $known > 0 ? (float) $known : null;

            $counter = $version === 2 ? app(DevicePlaybackCounter::class)->get($device, $item->id) : null;

            return ($counter ? ['maxPlays' => $item->max_plays, 'counterEpoch' => $counter['counter_epoch'], 'completedCount' => (int) $counter['completed_count']] : []) + ['id' => $item->id, 'sort_order' => $item->sort_order, 'mediaId' => $asset?->id,
                'mediaTransform' => MediaTransform::read($item->settings, $zone->fit_mode),
                'title' => $asset?->title ?? 'Media unavailable', 'type' => $asset?->type,
                'duration' => $item->duration_seconds ?? ($asset?->type === 'image' ? 10 : null),
                'durationMode' => $asset?->type === 'video' && $item->duration_seconds === null ? 'auto' : 'manual',
                'knownDuration' => $asset?->type === 'video' ? $known : null, 'available' => $file['available'] ?? false];
        }))->values()->all();
        $manifest = [
            'schemaVersion' => $version, 'state' => $version === 2 ? $currentPlan['state'] : ($schedule ? 'scheduled' : 'no_schedule'),
            'device' => ['publicId' => $device->public_id, 'name' => $device->name, 'platform' => $device->platform, 'playerVersion' => $device->player_version,
                'capabilities' => ['reported' => false, 'mediaTypes' => ['image', 'video']]],
            'output' => ['width' => $device->screen_width, 'height' => $device->screen_height, 'orientation' => $device->orientation],
            'currentSchedule' => $version === 2 ? $currentPlan['schedule'] : $scheduleData($schedule), 'currentPlaylistId' => $version === 2 ? $currentPlan['playlistId'] : $schedule?->playlist_id,
            'nextTransitionUtc' => isset($segments[0]) ? (collect($segments)->first(fn ($s) => $s['start']->gt($now))['start'] ?? null)?->toIso8601String() : null,
            'planStartUtc' => $from->toIso8601String(), 'validUntilUtc' => $until->toIso8601String(),
            'schedulePlan' => $plan, 'playlists' => $layouts, 'media' => $media->values()->all(),
            'pollIntervalSeconds' => (int) config('player.poll_interval_seconds', 60),
        ];
        if ($version === 2) {
            $manifest['fallbackPlaylistId'] = $devicePlan['fallbackPlaylistId'];
            $manifest['fallbackSelection'] = $devicePlan['fallbackSelection'];
            $manifest['nextTransitionUtc'] = collect($plan)->first(fn ($e) => CarbonImmutable::parse($e['startUtc'])->gt($now))['startUtc'] ?? null;
            $manifest['commandId'] = $device->playlist_command_id;
        }
        // Include candidate rules as well as winners so shadowed priority/range edits invalidate sync.
        $rules = $resolver->eligible($device->company_id)->sortBy('id')->map($scheduleData)->values()->all();
        $revisionManifest = $manifest;
        foreach ($revisionManifest['playlists'] as &$layout) {
            foreach ($layout['pages'] as &$page) {
                foreach ($page['zones'] as &$zone) {
                    foreach ($zone['items'] as &$item) {
                        unset($item['completedCount']);
                    }
                }
            }
        }
        unset($layout,$page,$zone,$item);
        $manifest['revision'] = hash('sha256', json_encode($this->canonical(['manifest' => $revisionManifest, 'scheduleRules' => $version === 2 ? $devicePlan['rules'] : $rules]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if ($version === 2) {
            $grants = ['plan' => array_merge($plan, [$devicePlan['fallbackSelection']]), 'scheduleIds' => collect($plan)->push($devicePlan['fallbackSelection'])->pluck('schedule.id')->filter()->unique()->values()->all(), 'commands' => array_values(array_unique(array_filter(array_column($plan, 'commandId')))), 'playlistIds' => array_column($layouts, 'id'), 'items' => []];
            foreach ($layouts as $layout) {
                foreach ($layout['pages'] as $page) {
                    foreach ($page['zones'] as $zone) {
                        foreach ($zone['items'] as $item) {
                            $grants['items'][(string) $item['id']] = ['playlist_id' => $layout['id'], 'page_id' => $page['id'], 'zone_id' => $zone['id'], 'playlist_zone_item_id' => $item['id'], 'media_asset_id' => $item['mediaId'], 'counter_epoch' => $item['counterEpoch'], 'max_plays' => $item['maxPlays'], 'duration' => $item['duration'], 'names' => ['playlist' => $layout['name'], 'page' => $page['name'], 'zone' => $zone['name'], 'media' => $item['title'], 'device' => $device->name]];
                        }
                    }
                }
            }
            DB::table('device_playback_packages')->insertOrIgnore(['company_id' => $device->company_id, 'device_id' => $device->id, 'revision' => $manifest['revision'], 'grants' => json_encode($grants, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }
        $manifest['serverTimeUtc'] = $now->toIso8601String();

        return $manifest;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
