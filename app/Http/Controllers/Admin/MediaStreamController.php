<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class MediaStreamController extends Controller
{
    public function show(MediaAsset $media, WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        $companyId = $workspaceService->companyId();
        abort_if($companyId === null, 403, 'Select a company workspace to access media.');
        abort_unless($workspaceService->company()?->is_active, 403, 'Workspace is unavailable.');

        MediaAsset::query()
            ->forCompany($companyId)
            ->whereKey($media->id)
            ->firstOrFail();

        if (! in_array($media->type, ['image', 'video', 'pdf'], true)) {
            abort(404);
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            abort(404);
        }

        $filePath = $disk->path($media->path);

        if (! is_file($filePath)) {
            abort(404);
        }

        $size = filesize($filePath);
        $start = 0;
        $end = $size - 1;
        $status = Response::HTTP_OK;

        $headers = [
            'Content-Type' => $media->mime_type ?: ($media->type === 'video' ? 'video/mp4' : ($disk->mimeType($media->path) ?: 'application/octet-stream')),
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', basename($media->file_name), 'media'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        $range = request()->header('Range');

        if ($range) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && $matches[2] === '') || $size === 0) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $status = Response::HTTP_PARTIAL_CONTENT;
            if ($matches[1] === '') {
                $suffix = (int) $matches[2];
                if ($suffix < 1) {
                    return response('', 416, ['Content-Range' => "bytes */{$size}"]);
                }
                $start = max(0, $size - $suffix);
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? min((int) $matches[2], $size - 1) : $size - 1;
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

        if (request()->isMethod('HEAD')) {
            return response('', $status, $headers);
        }

        return response()->stream(function () use ($filePath, $start, $length) {
            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);

            $bytesLeft = $length;
            $chunkSize = 1024 * 1024;

            while ($bytesLeft > 0 && ! feof($handle)) {
                $readLength = min($chunkSize, $bytesLeft);
                $buffer = fread($handle, $readLength);

                if ($buffer === false) {
                    break;
                }

                echo $buffer;

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                flush();

                $bytesLeft -= strlen($buffer);
            }

            fclose($handle);
        }, $status, $headers);
    }
}
