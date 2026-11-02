<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;

class PlaylistPlayback
{
    public function manifest(int $playlistId): array
    {
        $playlist = app(PlaylistWorkspace::class)->find($playlistId);
        $canMedia = Gate::allows('media.view');
        $pages = $playlist->pages()->orderBy('id')->with([
            'zones' => fn ($q) => $q->orderBy('id'),
            'zones.items' => fn ($q) => $q->orderBy('id'),
            'zones.items.mediaAsset' => fn ($q) => $q->where('company_id', $playlist->company_id)
                ->select(['id', 'company_id', 'title', 'type', 'duration', 'metadata']),
        ])->get();

        return [
            'id' => $playlist->id, 'name' => $playlist->name,
            'width' => $playlist->canvas_width, 'height' => $playlist->canvas_height,
            'background' => $playlist->background_color,
            'totalDuration' => (int) $pages->sum('duration_seconds'),
            'pages' => $pages->map(fn ($page) => [
                'id' => $page->id, 'name' => $page->name, 'sort_order' => $page->sort_order,
                'duration' => $page->duration_seconds,
                'background' => $page->background_color ?: $playlist->background_color,
                'zones' => $page->zones->map(fn ($zone) => [
                    'id' => $zone->id, 'name' => $zone->name,
                    'x' => $zone->x, 'y' => $zone->y, 'width' => $zone->width, 'height' => $zone->height,
                    'z_index' => $zone->z_index, 'visible' => $zone->is_visible, 'fit_mode' => $zone->fit_mode,
                    'items' => $zone->items->map(function ($item) use ($canMedia) {
                        $asset = $canMedia ? $item->mediaAsset : null;
                        $supported = $asset && in_array($asset->type, ['image', 'video'], true);
                        $known = $asset?->duration ?: ($asset?->metadata['duration'] ?? null);
                        $known = is_numeric($known) && is_finite((float) $known) && $known > 0 ? (float) $known : null;

                        return [
                            'id' => $item->id, 'sort_order' => $item->sort_order,
                            'title' => $supported ? $asset->title : 'Media unavailable',
                            'type' => $supported ? $asset->type : null,
                            'src' => $supported ? route('admin.media-assets.stream', $asset->id) : null,
                            'duration' => $item->duration_seconds ?? ($asset?->type === 'image' ? 10 : null),
                            'knownDuration' => $supported && $asset->type === 'video' ? $known : null,
                            'available' => (bool) $supported,
                        ];
                    })->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
