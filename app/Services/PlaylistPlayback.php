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

        $playlist->setRelation('pages', $pages);

        return app(PlaylistLayout::class)->serialize($playlist, function ($item) use ($canMedia) {
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
        });
    }
}
