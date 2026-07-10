<?php

namespace App\Livewire\Media;

use App\Models\MediaAsset;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaTable extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $activeTab = 'my_media';

    public string $search = '';
    public string $type = '';

    public array $files = [];

    public bool $isUploadPaused = false;

    public array $selectedMediaIds = [];

    public bool $showPreview = false;
    public ?int $previewMediaId = null;
    public int $previewToken = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatingType(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function toggleSelect(int $id): void
    {
        $id = (string) $id;

        if (in_array($id, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds = array_values(array_filter($this->selectedMediaIds, fn($selectedId) => $selectedId !== $id));

            return;
        }

        $this->selectedMediaIds[] = $id;
    }

    public function clearSelection(): void
    {
        $this->selectedMediaIds = [];
    }

    public function openPreview(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.view');

        MediaAsset::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $this->previewMediaId = $id;
        $this->previewToken++;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewMediaId = null;
    }

    public function previewNext(WorkspaceService $workspaceService): void
    {
        $ids = $this->previewIds($workspaceService);

        if (empty($ids)) {
            return;
        }

        $currentIndex = array_search($this->previewMediaId, $ids, true);

        if ($currentIndex === false || $currentIndex === count($ids) - 1) {
            $nextId = $ids[0];
        } else {
            $nextId = $ids[$currentIndex + 1];
        }

        $this->openPreview($nextId, $workspaceService);
    }

    public function previewPrevious(WorkspaceService $workspaceService): void
    {
        $ids = $this->previewIds($workspaceService);

        if (empty($ids)) {
            return;
        }

        $currentIndex = array_search($this->previewMediaId, $ids, true);

        if ($currentIndex === false || $currentIndex === 0) {
            $previousId = $ids[count($ids) - 1];
        } else {
            $previousId = $ids[$currentIndex - 1];
        }

        $this->openPreview($previousId, $workspaceService);
    }

    private function previewIds(WorkspaceService $workspaceService): array
    {
        return $this->mediaAssetsQuery($workspaceService)->latest()->pluck('id')->map(fn($id) => (int) $id)->toArray();
    }

    public function selectCurrentPage(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.view');

        $ids = $this->mediaAssetsQuery($workspaceService)->latest()->paginate(12)->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $this->selectedMediaIds = array_values(array_unique([...$this->selectedMediaIds, ...$ids]));
    }

    public function deleteSelected(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.delete');

        $ids = collect($this->selectedMediaIds)->map(fn($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $mediaAssets = MediaAsset::query()->forCompany($workspaceService->companyId())->whereKey($ids->all())->get();

        foreach ($mediaAssets as $media) {
            $disk = $media->disk;
            $path = $media->path;

            $media->deleteOrFail();

            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        $this->clearSelection();

        session()->flash('success', 'Selected media deleted successfully.');
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['my_media', 'upload', 'shared'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function updatedFiles(): void
    {
        if (empty($this->files)) {
            return;
        }

        $this->isUploadPaused = false;

        $this->saveFiles(app(WorkspaceService::class));
    }

    public function clearFiles(): void
    {
        $this->files = [];
        $this->isUploadPaused = false;
        $this->resetValidation();
    }

    public function pauseAllUploads(): void
    {
        $this->isUploadPaused = true;
    }

    public function startAllUploads(): void
    {
        $this->isUploadPaused = false;
    }

    public function delete(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.delete');

        $media = MediaAsset::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $disk = $media->disk;
        $path = $media->path;

        $media->deleteOrFail();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        session()->flash('success', 'Media deleted successfully.');
    }

    private function detectType(?string $mimeType, ?string $extension): string
    {
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType && str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($extension === 'pdf') {
            return 'pdf';
        }

        return 'other';
    }

    private function mediaAssetsQuery(WorkspaceService $workspaceService)
    {
        return MediaAsset::query()
            ->with(['company', 'uploader'])
            ->forCompany($workspaceService->companyId())
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('title', 'like', '%' . $this->search . '%')->orWhere('original_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->type, function ($query) {
                $query->where('type', $this->type);
            });
    }

    public function render(WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        $mediaAssets = $this->mediaAssetsQuery($workspaceService)->latest()->paginate(12);

        $previewMedia = null;

        if ($this->previewMediaId) {
            $previewMedia = MediaAsset::query()
                ->with(['company', 'uploader'])
                ->forCompany($workspaceService->companyId())
                ->find($this->previewMediaId);
        }

        return view('livewire.media.media-table', [
            'mediaAssets' => $mediaAssets,
            'previewMedia' => $previewMedia,
            'isAllCompanies' => $workspaceService->isAllCompanies(),
        ]);
    }
}
