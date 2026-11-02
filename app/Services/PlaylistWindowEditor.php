<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\PlaylistZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlaylistWindowEditor
{
    private function page(int $playlistId, int $pageId): array
    {
        Gate::authorize('playlists.edit');
        $playlist = app(PlaylistWorkspace::class)->find($playlistId, true);

        return [$playlist, $playlist->pages()->findOrFail($pageId)];
    }

    private function media(Playlist $playlist, int $id): MediaAsset
    {
        Gate::authorize('media.view');

        return MediaAsset::query()->where('company_id', $playlist->company_id)
            ->whereIn('type', ['image', 'video'])->lockForUpdate()->findOrFail($id);
    }

    private function geometry(array $data, Playlist $playlist): array
    {
        $valid = validator($data, [
            'x' => ['required', 'integer', 'between:0,16384'],
            'y' => ['required', 'integer', 'between:0,16384'],
            'width' => ['required', 'integer', 'between:1,16384'],
            'height' => ['required', 'integer', 'between:1,16384'],
        ])->validate();
        if ($playlist->canvas_width < $valid['x'] + $valid['width'] || $playlist->canvas_height < $valid['y'] + $valid['height']) {
            throw ValidationException::withMessages(['geometry' => 'Window must fit inside the saved canvas.']);
        }

        return array_map('intval', $valid);
    }

    public function drop(int $playlistId, int $pageId, int $mediaId, array $geometry, string $token): PlaylistZone
    {
        return DB::transaction(function () use ($playlistId, $pageId, $mediaId, $geometry, $token) {
            [$playlist, $page] = $this->page($playlistId, $pageId);
            $media = $this->media($playlist, $mediaId);
            validator(['token' => $token], ['token' => ['required', 'uuid']])->validate();
            // The parent row lock serializes retries. Keep the request ID on the first item, using existing JSON storage.
            $existing = $page->zones()->whereHas('items', fn ($q) => $q->where('settings->editor_request_id', $token))->first();
            if ($existing) {
                return $existing;
            }
            $box = $this->geometry($geometry, $playlist);
            $zone = $page->zones()->create($box + [
                'name' => mb_substr($media->title, 0, 100),
                'z_index' => (int) $page->zones()->max('z_index') + 1,
                'keep_aspect_ratio' => true, 'fit_mode' => 'contain',
            ]);
            $zone->items()->create(['media_asset_id' => $media->id, 'sort_order' => 1, 'duration_seconds' => $media->type === 'image' ? 10 : null, 'settings' => ['editor_request_id' => $token]]);

            return $zone;
        });
    }

    public function update(int $playlistId, int $pageId, int $zoneId, array $data): PlaylistZone
    {
        return DB::transaction(function () use ($playlistId, $pageId, $zoneId, $data) {
            [$playlist, $page] = $this->page($playlistId, $pageId);
            $zone = $page->zones()->findOrFail($zoneId);
            $valid = validator($data, [
                'name' => ['sometimes', 'required', 'string', 'max:100'],
                'x' => ['sometimes', 'required', 'integer', 'between:0,16384'],
                'y' => ['sometimes', 'required', 'integer', 'between:0,16384'],
                'width' => ['sometimes', 'required', 'integer', 'between:1,16384'],
                'height' => ['sometimes', 'required', 'integer', 'between:1,16384'],
                'keep_aspect_ratio' => ['sometimes', 'required', 'boolean'],
                'fit_mode' => ['sometimes', 'required', 'in:contain,cover,stretch'],
                'z_index' => ['sometimes', 'required', 'integer', 'between:-1000000,1000000'],
                'is_visible' => ['sometimes', 'required', 'boolean'],
                'is_locked' => ['sometimes', 'required', 'boolean'],
            ])->validate();
            if (isset($valid['name'])) {
                $valid['name'] = trim($valid['name']);
                validator($valid, ['name' => ['required']])->validate();
            }
            $box = array_intersect_key($valid, array_flip(['x', 'y', 'width', 'height'])) + $zone->only(['x', 'y', 'width', 'height']);
            $geometryChanged = collect($box)->contains(fn ($value, $key) => (int) $value !== (int) $zone->$key);
            if ($geometryChanged) {
                if ($zone->is_locked) {
                    throw ValidationException::withMessages(['geometry' => 'Unlock the Window before changing its position or size.']);
                }
                $this->geometry($box, $playlist);
                $keep = $valid['keep_aspect_ratio'] ?? $zone->keep_aspect_ratio;
                if ($keep && ($box['width'] != $zone->width || $box['height'] != $zone->height)) {
                    $ratio = $zone->width / $zone->height;
                    if (abs($box['width'] - $box['height'] * $ratio) > max(1.01, $ratio)) {
                        throw ValidationException::withMessages(['geometry' => 'Resize must preserve the Window aspect ratio.']);
                    }
                }
            }
            $zone->update($valid);

            return $zone->fresh();
        });
    }

    public function delete(int $playlistId, int $pageId, int $zoneId): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId) {
            [, $page] = $this->page($playlistId, $pageId);
            $page->zones()->findOrFail($zoneId)->delete();
        });
    }

    public function addItem(int $playlistId, int $pageId, int $zoneId, int $mediaId, string $token): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId, $mediaId, $token) {
            [$playlist, $page] = $this->page($playlistId, $pageId);
            $zone = $page->zones()->findOrFail($zoneId);
            $media = $this->media($playlist, $mediaId);
            validator(['token' => $token], ['token' => ['required', 'uuid']])->validate();
            if (! $zone->items()->where('settings->editor_request_id', $token)->exists()) {
                $zone->items()->create(['media_asset_id' => $media->id, 'sort_order' => (int) $zone->items()->max('sort_order') + 1, 'duration_seconds' => $media->type === 'image' ? 10 : null, 'settings' => ['editor_request_id' => $token]]);
            }
        });
    }

    public function updateItemDuration(int $playlistId, int $pageId, int $zoneId, int $itemId, $seconds): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId, $itemId, $seconds) {
            [$playlist, $page] = $this->page($playlistId, $pageId);
            $item = $page->zones()->findOrFail($zoneId)->items()->findOrFail($itemId);
            $media = MediaAsset::where('company_id', $playlist->company_id)->whereIn('type', ['image', 'video'])->findOrFail($item->media_asset_id);
            validator(['duration' => $seconds], ['duration' => $seconds === null && $media->type === 'video'
                ? ['nullable'] : ['required', 'integer', 'between:1,86400']])->validate();
            $item->update(['duration_seconds' => $seconds]);
        });
    }

    public function removeItem(int $playlistId, int $pageId, int $zoneId, int $itemId): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId, $itemId) {
            [, $page] = $this->page($playlistId, $pageId);
            $zone = $page->zones()->findOrFail($zoneId);
            $zone->items()->findOrFail($itemId)->delete();
        });
    }

    public function reorder(int $playlistId, int $pageId, int $zoneId, array $ids): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId, $ids) {
            [, $page] = $this->page($playlistId, $pageId);
            $zone = $page->zones()->findOrFail($zoneId);
            validator(['ids' => $ids], ['ids' => ['required', 'array', 'list'], 'ids.*' => ['required', 'integer', 'distinct']])->validate();
            $expected = $zone->items()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($given !== $expected) {
                throw ValidationException::withMessages(['items' => 'Supply every item from this Window exactly once.']);
            }
            foreach ($ids as $order => $id) {
                $zone->items()->whereKey($id)->update(['sort_order' => $order + 1]);
            }
        });
    }

    public function layer(int $playlistId, int $pageId, int $zoneId, string $direction): void
    {
        DB::transaction(function () use ($playlistId, $pageId, $zoneId, $direction) {
            [, $page] = $this->page($playlistId, $pageId);
            validator(['direction' => $direction], ['direction' => ['required', 'in:forward,backward,front,back']])->validate();
            $page->zones()->findOrFail($zoneId);
            $ids = $page->zones()->reorder()->orderBy('z_index')->orderBy('id')->pluck('id')->all();
            $at = array_search($zoneId, $ids);
            $to = match ($direction) {
                'forward' => min(count($ids) - 1, $at + 1), 'backward' => max(0, $at - 1), 'front' => count($ids) - 1, 'back' => 0
            };
            array_splice($ids, $at, 1);
            array_splice($ids, $to, 0, [$zoneId]);
            foreach ($ids as $index => $id) {
                $page->zones()->whereKey($id)->update(['z_index' => $index]);
            }
        });
    }
}
