<?php

namespace App\Livewire\Playlists;

use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\PlaylistZone;
use App\Services\PlaylistWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PlaylistStudio extends Component
{
    use InteractsWithStudioWindows;
    use WithPagination;

    #[Locked]
    public int $playlistId;

    #[Locked]
    public ?int $activePageId = null;

    public string $name = '';

    public $canvasWidth = 1920;

    public $canvasHeight = 1080;

    public string $backgroundColor = '#000000';

    public string $pageName = '';

    public $pageDuration = 15;

    public string $mediaSearch = '';

    public string $mediaType = '';

    public function mount(int $playlistId): void
    {
        $this->playlistId = $playlistId;
        $playlist = $this->playlist();
        $this->name = $playlist->name;
        $this->canvasWidth = $playlist->canvas_width;
        $this->canvasHeight = $playlist->canvas_height;
        $this->backgroundColor = $playlist->background_color;
        $page = $playlist->pages()->first();
        if ($page) {
            $this->selectPage($page->id);
        }
    }

    private function playlist(bool $lock = false): Playlist
    {
        return app(PlaylistWorkspace::class)->find($this->playlistId, $lock);
    }

    public function selectPage(int $id): void
    {
        $page = $this->playlist()->pages()->findOrFail($id);
        $this->activePageId = $page->id;
        $this->pageName = $page->name;
        $this->pageDuration = $page->duration_seconds;
        $this->resetValidation();
    }

    public function save(bool $allowOverflow = false): bool
    {
        Gate::authorize('playlists.edit');
        $this->playlist();
        $this->name = trim($this->name);
        $this->pageName = trim($this->pageName);
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'canvasWidth' => ['required', 'integer', 'between:64,16384'],
            'canvasHeight' => ['required', 'integer', 'between:64,16384'],
            'backgroundColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
        if ($this->activePageId !== null) {
            $rules += [
                'pageName' => ['required', 'string', 'max:100'],
                'pageDuration' => ['required', 'integer', 'between:1,86400'],
            ];
        }
        $data = $this->validate($rules);
        DB::transaction(function () use ($data, $allowOverflow) {
            $playlist = $this->playlist(true);
            $this->checkCanvasBounds($playlist, (int) $data['canvasWidth'], (int) $data['canvasHeight'], $allowOverflow);
            $playlist->update([
                'name' => $data['name'],
                'canvas_width' => $data['canvasWidth'],
                'canvas_height' => $data['canvasHeight'],
                'background_color' => $data['backgroundColor'],
            ]);
            if ($this->activePageId !== null) {
                $playlist->pages()->findOrFail($this->activePageId)->update([
                    'name' => $data['pageName'], 'duration_seconds' => $data['pageDuration'],
                ]);
            }
        });
        $this->dispatch('studio-saved', width: (int) $data['canvasWidth'], height: (int) $data['canvasHeight']);
        session()->flash('success', 'Playlist and page properties saved.');

        return true;
    }

    public function addPage(): void
    {
        Gate::authorize('playlists.edit');
        $id = DB::transaction(function () {
            $playlist = $this->playlist(true);
            $order = (int) $playlist->pages()->max('sort_order') + 1;

            return $playlist->pages()->create([
                'name' => 'Page '.$order, 'sort_order' => $order,
                'duration_seconds' => 15, 'background_color' => $playlist->background_color,
            ])->id;
        });
        $this->selectPage($id);
        session()->flash('success', 'Page added.');
    }

    public function deletePage(int $id): void
    {
        Gate::authorize('playlists.edit');
        $deleted = DB::transaction(function () use ($id) {
            // Serialize add/delete against this parent so simultaneous requests cannot remove the last page.
            $playlist = $this->playlist(true);
            $page = $playlist->pages()->findOrFail($id);
            if ($playlist->pages()->count() <= 1) {
                $this->addError('pages', 'A playlist must keep at least one page.');

                return false;
            }
            $page->delete();

            return true;
        });
        if ($deleted) {
            if ($this->activePageId === $id) {
                $this->selectPage($this->playlist()->pages()->firstOrFail()->id);
            }
            session()->flash('success', 'Page deleted.');
        }
    }

    public function updatedMediaSearch(): void
    {
        $this->playlist();
        $this->validateOnly('mediaSearch', ['mediaSearch' => ['string', 'max:200']]);
        $this->resetPage('mediaPage');
    }

    public function updatedMediaType(): void
    {
        $this->playlist();
        $this->validateOnly('mediaType', ['mediaType' => ['nullable', 'in:image,video']]);
        $this->resetPage('mediaPage');
    }

    public function render()
    {
        $playlist = $this->playlist();
        $pages = $playlist->pages()->withCount('zones')->get();
        $activePage = $this->activePageId === null ? null : $playlist->pages()
            ->with(['zones.items.mediaAsset' => fn ($q) => $q->where('company_id', $playlist->company_id)])->findOrFail($this->activePageId);
        $media = null;
        if (Gate::allows('media.view')) {
            $media = MediaAsset::query()->where('company_id', $playlist->company_id)
                ->whereIn('type', ['image', 'video'])
                ->when($this->mediaSearch !== '', fn ($q) => $q->where('title', 'like', '%'.$this->mediaSearch.'%'))
                ->when(in_array($this->mediaType, ['image', 'video'], true), fn ($q) => $q->where('type', $this->mediaType))
                ->latest()->paginate(12, ['*'], 'mediaPage');
        }

        $editorData = [
            'pageId' => $this->activePageId,
            'width' => $playlist->canvas_width, 'height' => $playlist->canvas_height,
            'canEdit' => Gate::allows('playlists.edit'), 'canMedia' => Gate::allows('media.view'),
            'extentX' => (int) PlaylistZone::whereIn('playlist_page_id', $pages->pluck('id'))->selectRaw('MAX(x + width) AS extent')->value('extent'),
            'extentY' => (int) PlaylistZone::whereIn('playlist_page_id', $pages->pluck('id'))->selectRaw('MAX(y + height) AS extent')->value('extent'),
            'zones' => $activePage?->zones->map(fn ($zone) => $zone->only(['id', 'name', 'x', 'y', 'width', 'height', 'z_index', 'keep_aspect_ratio', 'fit_mode', 'is_visible', 'is_locked']) + [
                'items' => $zone->items->map(fn ($item) => [
                    'id' => $item->id, 'sort_order' => $item->sort_order,
                    'title' => Gate::allows('media.view') ? ($item->mediaAsset?->title ?? 'Unavailable media') : 'Media',
                ])->values()->all(),
            ])->values()->all() ?? [],
        ];
        $this->dispatch('studio-editor-sync', data: $editorData);

        return view('livewire.playlists.playlist-studio', compact('playlist', 'pages', 'activePage', 'media', 'editorData'));
    }
}
