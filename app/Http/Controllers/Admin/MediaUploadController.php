<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MediaUploadController extends Controller
{
    public function store(Request $request, WorkspaceService $workspaceService)
    {
        Gate::authorize('media.create');

        $companyId = $workspaceService->companyId();

        if (!$companyId) {
            return response()->json(
                [
                    'message' => 'Please select a workspace before uploading media.',
                ],
                422,
            );
        }

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*' => ['required', 'file', 'max:204800', 'mimes:jpg,jpeg,png,webp,gif,bmp,mp4,mov,avi,mpeg,mpg,wmv,m4v,pdf'],
        ]);

        $uploaded = [];

        foreach ($validated['files'] as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $type = $this->detectType($mimeType, $extension);

            if (!in_array($type, ['image', 'video', 'pdf'], true)) {
                continue;
            }

            $fileName = now()->format('YmdHis') . '_' . Str::random(12) . '.' . $extension;

            $path = $file->storeAs('companies/' . $companyId . '/media/' . now()->format('Y/m'), $fileName, 'public');

            $media = MediaAsset::create([
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
                    'uploaded_from' => 'direct_xhr_upload',
                ],
                'is_active' => true,
            ]);

            $uploaded[] = [
                'id' => $media->id,
                'title' => $media->title,
                'type' => $media->type,
            ];
        }

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'uploaded' => $uploaded,
        ]);
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
}
