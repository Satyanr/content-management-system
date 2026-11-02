<?php

namespace App\Services;

use App\Models\Playlist;

/** Pure serialization: caller loads scoped relations and authorizes each media item. */
class PlaylistLayout
{
    public function serialize(Playlist $playlist, callable $item): array
    {
        $pages = $playlist->pages->sortBy([['sort_order', 'asc'], ['id', 'asc']]);

        return [
            'id' => $playlist->id, 'name' => $playlist->name,
            'width' => $playlist->canvas_width, 'height' => $playlist->canvas_height,
            'background' => $playlist->background_color,
            'totalDuration' => (int) $pages->sum('duration_seconds'),
            'pages' => $pages->map(fn ($page) => [
                'id' => $page->id, 'name' => $page->name, 'sort_order' => $page->sort_order,
                'duration' => $page->duration_seconds,
                'background' => $page->background_color ?: $playlist->background_color,
                'zones' => $page->zones->sortBy([['z_index', 'asc'], ['id', 'asc']])->map(fn ($zone) => [
                    'id' => $zone->id, 'name' => $zone->name,
                    'x' => $zone->x, 'y' => $zone->y, 'width' => $zone->width, 'height' => $zone->height,
                    'z_index' => $zone->z_index, 'visible' => $zone->is_visible, 'fit_mode' => $zone->fit_mode, 'keep_aspect_ratio' => $zone->keep_aspect_ratio,
                    'items' => $zone->items->sortBy([['sort_order', 'asc'], ['id', 'asc']])->map(fn ($entry) => $item($entry, $zone))->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
