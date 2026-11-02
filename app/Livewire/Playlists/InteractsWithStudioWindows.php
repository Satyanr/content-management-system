<?php

namespace App\Livewire\Playlists;

use App\Models\Playlist;
use App\Services\PlaylistPlayback;
use App\Services\PlaylistWindowEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithStudioWindows
{
    public function createWindow(int $pageId): bool
    {
        $zone = $this->windowEditor($pageId)->create($this->playlistId, $pageId);
        $this->dispatch('studio-select-window', id: $zone->id);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Window baru dibuat. Tambahkan media untuk mengisinya.');

        return true;
    }

    public function updateMediaTransform(int $pageId, int $zoneId, int $itemId, array $transform): bool
    {
        $this->windowEditor($pageId)->transform($this->playlistId, $pageId, $zoneId, $itemId, $transform);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Ukuran dan posisi isi media disimpan.');

        return true;
    }

    private function windowEditor(int $pageId): PlaylistWindowEditor
    {
        Gate::authorize('playlists.edit');
        $this->playlist()->pages()->findOrFail($pageId);
        abort_unless($this->activePageId === $pageId, 409, 'The active Page changed. Reopen the Window.');

        return app(PlaylistWindowEditor::class);
    }

    public function dropMedia(int $pageId, int $mediaId, array $box, string $token): bool
    {
        $zone = $this->windowEditor($pageId)->drop($this->playlistId, $pageId, $mediaId, $box, $token);
        $this->dispatch('studio-select-window', id: $zone->id);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Media placed in a new Window.');

        return true;
    }

    public function updateWindow(int $pageId, int $zoneId, array $data): bool
    {
        $this->windowEditor($pageId)->update($this->playlistId, $pageId, $zoneId, $data);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Window saved.');

        return true;
    }

    public function deleteWindow(int $pageId, int $zoneId): bool
    {
        $this->windowEditor($pageId)->delete($this->playlistId, $pageId, $zoneId);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Window deleted. Media files were retained.');

        return true;
    }

    public function addWindowMedia(int $pageId, int $zoneId, int $mediaId, string $token): bool
    {
        $this->windowEditor($pageId)->addItem($this->playlistId, $pageId, $zoneId, $mediaId, $token);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Media added to Window.');

        return true;
    }

    public function updateItemDuration(int $pageId, int $zoneId, int $itemId, $seconds): bool
    {
        $this->windowEditor($pageId)->updateItemDuration($this->playlistId, $pageId, $zoneId, $itemId, $seconds);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Item duration saved.');

        return true;
    }

    public function resetItemDurationToAuto(int $pageId, int $zoneId, int $itemId): bool
    {
        return $this->updateItemDuration($pageId, $zoneId, $itemId, null);
    }

    public function getPlaybackManifest(): array
    {
        return app(PlaylistPlayback::class)->manifest($this->playlistId);
    }

    public function removeWindowMedia(int $pageId, int $zoneId, int $itemId): bool
    {
        $this->windowEditor($pageId)->removeItem($this->playlistId, $pageId, $zoneId, $itemId);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Item removed. Source file retained.');

        return true;
    }

    public function reorderWindowMedia(int $pageId, int $zoneId, array $ids): bool
    {
        $this->windowEditor($pageId)->reorder($this->playlistId, $pageId, $zoneId, $ids);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Media order saved.');

        return true;
    }

    public function changeWindowLayer(int $pageId, int $zoneId, string $direction): bool
    {
        $this->windowEditor($pageId)->layer($this->playlistId, $pageId, $zoneId, $direction);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Layer saved.');

        return true;
    }

    private function checkCanvasBounds(Playlist $playlist, int $width, int $height, bool $confirmed): void
    {
        $outside = $playlist->pages()->whereHas('zones', fn ($q) => $q->whereRaw('x + width > ?', [$width])->orWhereRaw('y + height > ?', [$height]))->exists();
        if ($outside && ! $confirmed) {
            throw ValidationException::withMessages(['canvasBounds' => 'Shrinking this canvas leaves Windows outside its bounds. Confirm the warning to keep their original geometry.']);
        }
    }

    public function resizeCanvas($width, $height, bool $confirmed = false): bool
    {
        Gate::authorize('playlists.edit');
        $this->playlist();
        $valid = validator(compact('width', 'height'), [
            'width' => ['required', 'integer', 'between:64,16384'],
            'height' => ['required', 'integer', 'between:64,16384'],
        ])->validate();
        DB::transaction(function () use ($valid, $confirmed) {
            $playlist = $this->playlist(true);
            $this->checkCanvasBounds($playlist, (int) $valid['width'], (int) $valid['height'], $confirmed);
            $playlist->update(['canvas_width' => $valid['width'], 'canvas_height' => $valid['height']]);
        });
        $this->canvasWidth = (int) $valid['width'];
        $this->canvasHeight = (int) $valid['height'];
        $this->dispatch('studio-canvas-resized', width: $this->canvasWidth, height: $this->canvasHeight);
        $this->dispatch('studio-playback-invalidated');
        session()->flash('success', 'Canvas resized. Existing Window geometry was preserved.');

        return true;
    }
}
