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

    public string $search = '';
    public string $type = '';

    public $file;
    public string $title = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset('file', 'title');
        $this->resetValidation();
    }

    public function saveMedia(WorkspaceService $workspaceService): void
    {
        Gate::authorize('media.create');

        $companyId = $workspaceService->companyId();

        if (!$companyId) {
            session()->flash('error', 'Please select a workspace before uploading media.');
            return;
        }

        $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,pdf'],
        ]);

        $originalName = $this->file->getClientOriginalName();
        $extension = strtolower($this->file->getClientOriginalExtension());
        $mimeType = $this->file->getMimeType();
        $size = $this->file->getSize();

        $fileName = now()->format('YmdHis') . '_' . Str::random(12) . '.' . $extension;

        $path = $this->file->storeAs('companies/' . $companyId . '/media/' . now()->format('Y/m'), $fileName, 'public');

        MediaAsset::create([
            'company_id' => $companyId,
            'uploaded_by' => Auth::id(),
            'title' => $this->title ?: pathinfo($originalName, PATHINFO_FILENAME),
            'original_name' => $originalName,
            'file_name' => $fileName,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'type' => $this->detectType($mimeType, $extension),
            'size' => $size,
            'metadata' => [
                'uploaded_from' => 'media_library',
            ],
            'is_active' => true,
        ]);

        $this->resetForm();

        session()->flash('success', 'Media uploaded successfully.');
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

    public function render(WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        $mediaAssets = MediaAsset::query()
            ->with(['company', 'uploader'])
            ->forCompany($workspaceService->companyId())
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('title', 'like', '%' . $this->search . '%')->orWhere('original_name', 'like', '%' . $this->search . '%');
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
