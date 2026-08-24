<?php

namespace App\Livewire\Media;

use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class MediaTable extends Component
{
    use WithPagination;

    public string $activeTab = 'my_media';

    public string $search = '';
    public string $type = '';

    public bool $showMoveMediaModal = false;

    public ?int $moveTargetFolderId = null;

    /*
|--------------------------------------------------------------------------
| Rename
|--------------------------------------------------------------------------
*/

    public bool $showRenameFolderModal = false;
    public ?int $renameFolderId = null;
    public string $renameFolderName = '';

    public bool $showRenameMediaModal = false;
    public ?int $renameMediaId = null;
    public string $renameMediaTitle = '';

    public array $selectedMediaIds = [];

    public bool $showPreview = false;
    public ?int $previewMediaId = null;
    public int $previewToken = 0;

    /*
    |--------------------------------------------------------------------------
    | Folder
    |--------------------------------------------------------------------------
    */

    public ?int $currentFolderId = null;

    public bool $showCreateFolderForm = false;
    public string $newFolderName = '';

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

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['my_media', 'upload', 'shared'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | Folder Management
    |--------------------------------------------------------------------------
    */

    public function openRenameFolderModal(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $folder = MediaFolder::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $this->renameFolderId = $folder->id;
        $this->renameFolderName = $folder->name;
        $this->showRenameFolderModal = true;

        $this->resetValidation();
    }

    public function closeRenameFolderModal(): void
    {
        $this->showRenameFolderModal = false;
        $this->renameFolderId = null;
        $this->renameFolderName = '';

        $this->resetValidation();
    }

    public function renameFolder(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $companyId = $workspaceService->companyId();

        if (!$companyId || !$this->renameFolderId) {
            return;
        }

        $this->renameFolderName = trim($this->renameFolderName);

        $this->validate([
            'renameFolderName' => ['required', 'string', 'max:100'],
        ]);

        $folder = MediaFolder::query()->forCompany($companyId)->findOrFail($this->renameFolderId);

        $duplicate = MediaFolder::query()->forCompany($companyId)->where('parent_id', $folder->parent_id)->where('name', $this->renameFolderName)->whereKeyNot($folder->id)->exists();

        if ($duplicate) {
            $this->addError('renameFolderName', 'A folder with this name already exists.');

            return;
        }

        $folder->update([
            'name' => $this->renameFolderName,
        ]);

        $this->closeRenameFolderModal();

        session()->flash('success', 'Folder renamed successfully.');
    }

    public function openRenameMediaModal(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $media = MediaAsset::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $this->renameMediaId = $media->id;
        $this->renameMediaTitle = $media->title;
        $this->showRenameMediaModal = true;

        $this->resetValidation();
    }

    public function closeRenameMediaModal(): void
    {
        $this->showRenameMediaModal = false;
        $this->renameMediaId = null;
        $this->renameMediaTitle = '';

        $this->resetValidation();
    }

    public function renameMedia(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $companyId = $workspaceService->companyId();

        if (!$companyId || !$this->renameMediaId) {
            return;
        }

        $this->renameMediaTitle = trim($this->renameMediaTitle);

        $this->validate([
            'renameMediaTitle' => ['required', 'string', 'max:255'],
        ]);

        $media = MediaAsset::query()->forCompany($companyId)->findOrFail($this->renameMediaId);

        $media->update([
            'title' => $this->renameMediaTitle,
        ]);

        $this->closeRenameMediaModal();

        session()->flash('success', 'Media renamed successfully.');
    }

    public function openCreateFolderForm(): void
    {
        Gate::authorize('media.create');

        $this->newFolderName = '';
        $this->showCreateFolderForm = true;
        $this->resetValidation();
    }

    public function closeCreateFolderForm(): void
    {
        $this->newFolderName = '';
        $this->showCreateFolderForm = false;
        $this->resetValidation();
    }

    public function createFolder(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.create');

        $companyId = $workspaceService->companyId();

        if (!$companyId) {
            session()->flash('error', 'Please select a workspace first.');

            return;
        }

        $this->validate([
            'newFolderName' => ['required', 'string', 'max:100'],
        ]);

        if ($this->currentFolderId) {
            MediaFolder::query()->forCompany($companyId)->findOrFail($this->currentFolderId);
        }

        $exists = MediaFolder::query()->forCompany($companyId)->where('parent_id', $this->currentFolderId)->where('name', $this->newFolderName)->exists();

        if ($exists) {
            $this->addError('newFolderName', 'A folder with this name already exists.');

            return;
        }

        MediaFolder::create([
            'company_id' => $companyId,
            'parent_id' => $this->currentFolderId,
            'created_by' => Auth::id(),
            'name' => trim($this->newFolderName),
            'is_active' => true,
        ]);

        $this->closeCreateFolderForm();

        session()->flash('success', 'Folder created successfully.');
    }

    public function openFolder(int $id, WorkspaceService $workspaceService): void
    {
        $folder = MediaFolder::query()->forCompany($workspaceService->companyId())->where('is_active', true)->findOrFail($id);

        $this->currentFolderId = $folder->id;

        $this->search = '';
        $this->type = '';

        $this->clearSelection();
        $this->resetPage();
    }

    public function goToRoot(): void
    {
        $this->currentFolderId = null;

        $this->search = '';
        $this->type = '';

        $this->clearSelection();
        $this->resetPage();
    }

    public function goToParent(WorkspaceService $workspaceService): void
    {
        if (!$this->currentFolderId) {
            return;
        }

        $folder = MediaFolder::query()->forCompany($workspaceService->companyId())->findOrFail($this->currentFolderId);

        $this->currentFolderId = $folder->parent_id;

        $this->clearSelection();
        $this->resetPage();
    }

    public function deleteFolder(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.delete');

        $folder = MediaFolder::query()
            ->forCompany($workspaceService->companyId())
            ->withCount(['children', 'mediaAssets'])
            ->findOrFail($id);

        if ($folder->children_count > 0 || $folder->media_assets_count > 0) {
            session()->flash('error', 'Folder cannot be deleted because it is not empty.');

            return;
        }

        $folder->deleteOrFail();

        session()->flash('success', 'Folder deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Selection
    |--------------------------------------------------------------------------
    */

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

    public function selectCurrentPage(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.view');

        $ids = $this->mediaAssetsQuery($workspaceService)->latest()->paginate(12)->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $this->selectedMediaIds = array_values(array_unique([...$this->selectedMediaIds, ...$ids]));
    }

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

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

        $index = array_search($this->previewMediaId, $ids, true);

        $nextId = $index === false || $index === count($ids) - 1 ? $ids[0] : $ids[$index + 1];

        $this->openPreview($nextId, $workspaceService);
    }

    public function previewPrevious(WorkspaceService $workspaceService): void
    {
        $ids = $this->previewIds($workspaceService);

        if (empty($ids)) {
            return;
        }

        $index = array_search($this->previewMediaId, $ids, true);

        $previousId = $index === false || $index === 0 ? $ids[count($ids) - 1] : $ids[$index - 1];

        $this->openPreview($previousId, $workspaceService);
    }

    private function previewIds(WorkspaceService $workspaceService): array
    {
        return $this->mediaAssetsQuery($workspaceService)->latest()->pluck('id')->map(fn($id) => (int) $id)->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Media
    |--------------------------------------------------------------------------
    */

    public function delete(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.delete');

        $media = MediaAsset::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $this->deleteMediaFile($media);

        session()->flash('success', 'Media deleted successfully.');
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
            $this->deleteMediaFile($media);
        }

        $this->clearSelection();

        session()->flash('success', 'Selected media deleted successfully.');
    }

    private function deleteMediaFile(MediaAsset $media): void
    {
        $disk = $media->disk;
        $path = $media->path;

        $media->deleteOrFail();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    private function mediaAssetsQuery(WorkspaceService $workspaceService)
    {
        return MediaAsset::query()
            ->with(['company', 'uploader', 'folder'])
            ->forCompany($workspaceService->companyId())
            ->when(
                $this->currentFolderId,
                function ($query) {
                    $query->where('media_folder_id', $this->currentFolderId);
                },
                function ($query) {
                    $query->whereNull('media_folder_id');
                },
            )
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('title', 'like', '%' . $this->search . '%')->orWhere('original_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->type, function ($query) {
                $query->where('type', $this->type);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render(WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        $companyId = $workspaceService->companyId();

        $folders = MediaFolder::query()
            ->forCompany($companyId)
            ->where('parent_id', $this->currentFolderId)
            ->where('is_active', true)
            ->withCount(['children', 'mediaAssets'])
            ->orderBy('name')
            ->get();

        $currentFolder = null;
        $breadcrumbs = collect();

        if ($this->currentFolderId) {
            $currentFolder = MediaFolder::query()->forCompany($companyId)->find($this->currentFolderId);

            $folder = $currentFolder;
            $safety = 0;

            while ($folder && $safety < 20) {
                $breadcrumbs->prepend($folder);

                $folder = $folder->parent_id ? MediaFolder::query()->forCompany($companyId)->find($folder->parent_id) : null;

                $safety++;
            }
        }

        $mediaAssets = $this->mediaAssetsQuery($workspaceService)->latest()->paginate(12);

        $previewMedia = null;

        if ($this->previewMediaId) {
            $previewMedia = MediaAsset::query()
                ->with(['company', 'uploader'])
                ->forCompany($companyId)
                ->find($this->previewMediaId);
        }

        $previewList = $this->mediaAssetsQuery($workspaceService)->latest()->limit(24)->get();

        $moveFolders = MediaFolder::query()->forCompany($companyId)->where('is_active', true)->with('parent')->orderBy('name')->get();

        return view('livewire.media.media-table', [
            'folders' => $folders,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $breadcrumbs,

            'mediaAssets' => $mediaAssets,
            'previewMedia' => $previewMedia,
            'previewList' => $previewList,

            'moveFolders' => $moveFolders,

            'isAllCompanies' => $workspaceService->isAllCompanies(),
        ]);
    }

    public function openMoveMediaModal(): void
    {
        Gate::authorize('media.edit');

        if (empty($this->selectedMediaIds)) {
            return;
        }

        $this->moveTargetFolderId = $this->currentFolderId;
        $this->showMoveMediaModal = true;
    }

    public function closeMoveMediaModal(): void
    {
        $this->showMoveMediaModal = false;
        $this->moveTargetFolderId = null;
    }

    public function moveSelectedMedia(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $companyId = $workspaceService->companyId();

        if (!$companyId) {
            session()->flash('error', 'Please select a workspace first.');

            return;
        }

        $ids = collect($this->selectedMediaIds)->map(fn($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            $this->closeMoveMediaModal();

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Validate Destination Folder
    |--------------------------------------------------------------------------
    */

        if ($this->moveTargetFolderId) {
            MediaFolder::query()->forCompany($companyId)->where('is_active', true)->findOrFail($this->moveTargetFolderId);
        }

        /*
    |--------------------------------------------------------------------------
    | Move Only Media Owned By Current Company
    |--------------------------------------------------------------------------
    */

        MediaAsset::query()
            ->forCompany($companyId)
            ->whereKey($ids->all())
            ->update([
                'media_folder_id' => $this->moveTargetFolderId,
            ]);

        $this->clearSelection();
        $this->closeMoveMediaModal();

        session()->flash('success', 'Selected media moved successfully.');
    }

    public function openMoveSingleMediaModal(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.edit');

        $media = MediaAsset::query()->forCompany($workspaceService->companyId())->findOrFail($id);

        $this->selectedMediaIds = [(string) $media->id];

        $this->moveTargetFolderId = $this->currentFolderId;

        $this->showMoveMediaModal = true;
    }
}
