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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['my_media', 'upload', 'shared'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetValidation();
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

    public function saveFiles(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.create');

        if ($this->isUploadPaused) {
            session()->flash('error', 'Upload is paused. Click Start All before uploading.');
            return;
        }

        $companyId = $workspaceService->companyId();

        if (! $companyId) {
            session()->flash('error', 'Please select a workspace before uploading media.');
            return;
        }

        $this->validate([
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*' => [
                'file',
                'max:204800',
                'mimes:jpg,jpeg,png,webp,gif,bmp,mp4,mov,avi,mpeg,wmv,pdf',
            ],
        ]);

        foreach ($this->files as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $type = $this->detectType($mimeType, $extension);

            if (! in_array($type, ['image', 'video', 'pdf'], true)) {
                continue;
            }

            $fileName = now()->format('YmdHis') . '_' . Str::random(12) . '.' . $extension;

            $path = $file->storeAs(
                'companies/' . $companyId . '/media/' . now()->format('Y/m'),
                $fileName,
                'public'
            );

            MediaAsset::create([
                'company_id' => $companyId,
                'uploaded_by' => Auth::id(),
                'title' => pathinfo($originalName, PATHINFO_FILENAME),
                'original_name' => $originalName,
                'file_name' => $fileName,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'type' => $type,
                'size' => $size,
                'metadata' => [
                    'uploaded_from' => 'content_library_upload_tab',
                ],
                'is_active' => true,
            ]);
        }

        $this->clearFiles();

        session()->flash('success', 'Media uploaded successfully.');

        $this->activeTab = 'my_media';
    }

    public function delete(int $id, WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.delete');

        $media = MediaAsset::query()
            ->forCompany($workspaceService->companyId())
            ->findOrFail($id);

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

    public function render(WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        $mediaAssets = MediaAsset::query()
            ->with(['company', 'uploader'])
            ->forCompany($workspaceService->companyId())
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('original_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->type, function ($query) {
                $query->where('type', $this->type);
            })
            ->latest()
            ->paginate(12);

        return view('livewire.media.media-table', [
            'mediaAssets' => $mediaAssets,
            'isAllCompanies' => $workspaceService->isAllCompanies(),
        ]);
    }
}