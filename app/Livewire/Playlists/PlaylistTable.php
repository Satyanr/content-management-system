<?php

namespace App\Livewire\Playlists;

use App\Models\Playlist;
use App\Services\WorkspaceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PlaylistTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    #[Locked]
    public ?int $formCompanyId = null;

    #[Locked]
    public ?int $playlistId = null;

    public string $name = '';

    public string $description = '';

    public $canvasWidth = 1920;

    public $canvasHeight = 1080;

    public string $backgroundColor = '#000000';

    public bool $isActive = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(WorkspaceService $workspaceService): void
    {
        Gate::authorize('playlists.create');
        $companyId = $this->requireCompany($workspaceService);
        $this->resetForm();
        $this->formCompanyId = $companyId;
        $this->showModal = true;
    }

    public function edit(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('playlists.edit');
        $companyId = $this->requireCompany($workspaceService);

        $playlist = Playlist::query()->forCompany($companyId)->findOrFail($id);

        $this->resetForm();
        $this->formCompanyId = $companyId;
        $this->playlistId = $playlist->id;
        $this->name = $playlist->name;
        $this->description = $playlist->description ?? '';
        $this->canvasWidth = $playlist->canvas_width;
        $this->canvasHeight = $playlist->canvas_height;
        $this->backgroundColor = $playlist->background_color;
        $this->isActive = $playlist->is_active;
        $this->showModal = true;
    }

    public function setCanvasPreset(int $width, int $height): void
    {
        Gate::authorize($this->playlistId !== null ? 'playlists.edit' : 'playlists.create');
        $this->authorizeForm(app(WorkspaceService::class));
        validator(compact('width', 'height'), [
            'width' => ['required', 'integer', 'between:64,16384'],
            'height' => ['required', 'integer', 'between:64,16384'],
        ])->validate();
        $this->canvasWidth = $width;
        $this->canvasHeight = $height;
    }

    public function save(WorkspaceService $workspaceService): void
    {
        $isEdit = $this->playlistId !== null;
        Gate::authorize($isEdit ? 'playlists.edit' : 'playlists.create');
        $companyId = $this->requireCompany($workspaceService);

        $this->authorizeForm($workspaceService);
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'canvasWidth' => ['required', 'integer', 'between:64,16384'],
            'canvasHeight' => ['required', 'integer', 'between:64,16384'],
            'backgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'isActive' => ['boolean'],
        ]);

        $data = [
            'name' => trim($validated['name']),
            'description' => trim($validated['description'] ?? '') ?: null,
            'canvas_width' => $validated['canvasWidth'],
            'canvas_height' => $validated['canvasHeight'],
            'background_color' => $validated['backgroundColor'],
            'is_active' => $validated['isActive'],
        ];

        if ($isEdit) {
            Playlist::query()->forCompany($companyId)
                ->findOrFail($this->playlistId)
                ->update($data);
            $message = 'Playlist updated successfully.';
        } else {
            DB::transaction(function () use ($companyId, $data): void {
                $playlist = Playlist::create($data + [
                    'company_id' => $companyId,
                    'created_by' => Auth::id(),
                ]);

                $playlist->pages()->create([
                    'name' => 'Page 1',
                    'sort_order' => 1,
                    'duration_seconds' => 15,
                    'background_color' => $data['background_color'],
                ]);
            });
            $message = 'Playlist created successfully.';
        }

        $this->closeModal();
        session()->flash('success', $message);
    }

    public function delete(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('playlists.delete');
        $companyId = $this->requireCompany($workspaceService);

        $playlist = Playlist::query()->forCompany($companyId)->findOrFail($id);
        if ($playlist->schedules()->exists()) {
            session()->flash('error', 'Playlist is used by a Schedule. Delete its schedules before deleting this Playlist.');

            return;
        }
        try {
            $playlist->deleteOrFail();
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            session()->flash('error', 'Playlist is referenced by a Schedule and cannot be deleted.');

            return;
        }
        $this->resetPage();
        session()->flash('success', 'Playlist deleted successfully.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formCompanyId = null;
        $this->playlistId = null;
        $this->name = '';
        $this->description = '';
        $this->canvasWidth = 1920;
        $this->canvasHeight = 1080;
        $this->backgroundColor = '#000000';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function authorizeForm(WorkspaceService $workspaceService): void
    {
        abort_unless($this->showModal && $this->formCompanyId === $this->requireCompany($workspaceService), 403, 'Reopen the form in the current workspace.');
    }

    private function requireCompany(WorkspaceService $workspaceService): int
    {
        $companyId = $workspaceService->companyId();
        abort_if($companyId === null, 403, 'Select a workspace first.');
        abort_unless($workspaceService->company()?->is_active, 403, 'Workspace is unavailable.');

        return $companyId;
    }

    public function render(WorkspaceService $workspaceService)
    {
        Gate::authorize('playlists.view');
        $companyId = $workspaceService->companyId();

        $playlists = Playlist::query()
            ->forCompany($companyId)
            ->withCount('pages')
            ->when($this->search !== '', fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(12);

        return view('livewire.playlists.playlist-table', [
            'playlists' => $playlists,
            'hasWorkspace' => $companyId !== null,
        ]);
    }
}
