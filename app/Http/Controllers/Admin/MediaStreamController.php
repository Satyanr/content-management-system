<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaStreamController extends Controller
{
    public function show(MediaAsset $media, WorkspaceService $workspaceService)
    {
        Gate::authorize('media.view');

        MediaAsset::query()
            ->forCompany($workspaceService->companyId())
            ->whereKey($media->id)
            ->firstOrFail();

        if ($media->type !== 'video') {
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
            'Content-Type' => $media->mime_type ?: 'video/mp4',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . addslashes($media->file_name) . '"',
            'Cache-Control' => 'public, max-age=31536000',
        ];

        $range = request()->header('Range');

        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            $status = Response::HTTP_PARTIAL_CONTENT;

            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }

            if ($matches[2] !== '') {
                $end = (int) $matches[2];
            }

            if ($end > $size - 1) {
                $end = $size - 1;
            }

            if ($start > $end || $start >= $size) {
                return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, [
                    'Content-Range' => "bytes */{$size}",
                ]);
            }

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

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