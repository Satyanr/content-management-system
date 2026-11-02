<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Playlist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeviceManifestService
{
    public function build(Device $device): array
    {
        return DB::transaction(fn () => $this->snapshot($device));
    }

    private function snapshot(Device $device): array
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
        $ids = collect($segments)->pluck('schedule.playlist_id')->filter()->unique()->values();
        $playlists = Playlist::forCompany($device->company_id)->where('is_active', true)->whereIn('id', $ids)->orderBy('id')->with([
            'pages.zones.items.mediaAsset' => fn ($q) => $q->where('company_id', $device->company_id)->where('is_active', true)->whereIn('type', ['image', 'video']),
        ])->get();
        abort_unless($playlists->count() === $ids->count(), 409);
        $assets = $playlists->flatMap(fn ($p) => $p->pages->flatMap(fn ($page) => $page->zones->flatMap(fn ($zone) => $zone->items->pluck('mediaAsset'))))
            ->filter()->unique('id')->sortBy('id')->values();
        $media = $assets->map(fn ($asset) => app(DeviceMediaFile::class)->metadata($asset))->keyBy('id');
        $layouts = $playlists->map(fn ($playlist) => app(PlaylistLayout::class)->serialize($playlist, function ($item, $zone) use ($media) {
            $asset = $item->mediaAsset;
            $file = $asset ? $media->get($asset->id) : null;
            $known = $asset?->duration ?: ($asset?->metadata['duration'] ?? null);
            $known = is_numeric($known) && is_finite((float) $known) && $known > 0 ? (float) $known : null;

            return ['id' => $item->id, 'sort_order' => $item->sort_order, 'mediaId' => $asset?->id,
                'mediaTransform' => MediaTransform::read($item->settings, $zone->fit_mode),
                'title' => $asset?->title ?? 'Media unavailable', 'type' => $asset?->type,
                'duration' => $item->duration_seconds ?? ($asset?->type === 'image' ? 10 : null),
                'durationMode' => $asset?->type === 'video' && $item->duration_seconds === null ? 'auto' : 'manual',
                'knownDuration' => $asset?->type === 'video' ? $known : null, 'available' => $file['available'] ?? false];
        }))->values()->all();
        $manifest = [
            'schemaVersion' => 1, 'state' => $schedule ? 'scheduled' : 'no_schedule',
            'device' => ['publicId' => $device->public_id, 'name' => $device->name, 'platform' => $device->platform, 'playerVersion' => $device->player_version,
                'capabilities' => ['reported' => false, 'mediaTypes' => ['image', 'video']]],
            'output' => ['width' => $device->screen_width, 'height' => $device->screen_height, 'orientation' => $device->orientation],
            'currentSchedule' => $scheduleData($schedule), 'currentPlaylistId' => $schedule?->playlist_id,
            'nextTransitionUtc' => isset($segments[0]) ? (collect($segments)->first(fn ($s) => $s['start']->gt($now))['start'] ?? null)?->toIso8601String() : null,
            'planStartUtc' => $from->toIso8601String(), 'validUntilUtc' => $until->toIso8601String(),
            'schedulePlan' => $plan, 'playlists' => $layouts, 'media' => $media->values()->all(),
            'pollIntervalSeconds' => (int) config('player.poll_interval_seconds', 60),
        ];
        // Include candidate rules as well as winners so shadowed priority/range edits invalidate sync.
        $rules = $resolver->eligible($device->company_id)->sortBy('id')->map($scheduleData)->values()->all();
        $manifest['revision'] = hash('sha256', json_encode($this->canonical(['manifest' => $manifest, 'scheduleRules' => $rules]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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
