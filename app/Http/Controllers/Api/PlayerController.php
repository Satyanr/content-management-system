<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\DeviceManifestService;
use App\Services\DeviceMediaFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PlayerController extends Controller
{
    private function error(string $code, int $status)
    {
        return response()->json(['message' => 'Player request could not be completed.', 'error' => ['code' => $code]], $status);
    }

    private function supported(Request $request): bool
    {
        return $request->header('X-Player-Schema-Version', '1') === '1';
    }

    private function matches(Request $request, string $etag): bool
    {
        foreach ($request->getETags() as $candidate) {
            if ($candidate === '*' || preg_replace('/^W\//', '', $candidate) === preg_replace('/^W\//', '', $etag)) {
                return true;
            }
        }

        return false;
    }

    public function manifest(Request $request, DeviceManifestService $service)
    {
        if (! $this->supported($request)) {
            return $this->error('unsupported_schema_version', 406);
        }
        $manifest = $service->build($request->attributes->get('device_credential')->device);
        // Weak validator represents semantic content, excluding the live server clock.
        $etag = 'W/"'.$manifest['revision'].'"';
        $headers = ['ETag' => $etag, 'X-Content-Revision' => $manifest['revision'], 'X-Server-Time-Utc' => $manifest['serverTimeUtc']];
        if ($this->matches($request, $etag)) {
            return response('', 304, $headers);
        }

        return response()->json($manifest, 200, $headers);
    }

    public function media(Request $request, string $media, DeviceManifestService $service, DeviceMediaFile $files)
    {
        if (! $this->supported($request)) {
            return $this->error('unsupported_schema_version', 406);
        }
        $device = $request->attributes->get('device_credential')->device;
        $asset = ctype_digit($media) ? MediaAsset::where('company_id', $device->company_id)->where('is_active', true)->find($media) : null;
        if (! $asset) {
            return $this->error('media_not_found', 404);
        }
        // Re-evaluate live scope before conditional responses and every Range request.
        $manifest = $service->build($device);
        $metadata = collect($manifest['media'])->firstWhere('id', $asset->id);
        if (! $metadata) {
            return $this->error('media_not_found', 404);
        }
        if ($request->hasHeader('X-Content-Revision') && $request->header('X-Content-Revision') !== $manifest['revision']) {
            return $this->error('sync_revision_changed', 409);
        }
        $file = $files->open($asset);
        if (! $file) {
            return $this->error('media_unavailable', 404);
        }
        $handle = $file['handle'];
        if ($metadata['checksum']['value'] !== $file['sha256'] || $metadata['size'] !== $file['size']) {
            fclose($handle);

            return $this->error('sync_revision_changed', 409);
        }
        $size = $file['size'];
        $etag = '"'.$file['sha256'].'"';
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($asset->file_name)) ?: 'media';
        $headers = ['Content-Type' => $metadata['mimeType'], 'Content-Length' => (string) $size, 'Accept-Ranges' => 'bytes',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name), 'ETag' => $etag,
            'X-Content-SHA256' => $file['sha256'], 'X-Content-Revision' => $manifest['revision']];
        if ($this->matches($request, $etag)) {
            fclose($handle);

            return response('', 304, array_diff_key($headers, ['Content-Length' => true]));
        }
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $request->header('Range');
        $ifRange = $request->header('If-Range');
        if ($request->isMethod('GET') && $range && (! $ifRange || $ifRange === $etag)) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $parts) || ($parts[1] === '' && $parts[2] === '') || $size === 0) {
                fclose($handle);

                return $this->error('invalid_range', 416)->header('Content-Range', 'bytes */'.$size);
            }
            if ($parts[1] === '') {
                $suffix = (int) $parts[2];
                $start = $suffix > 0 ? max(0, $size - $suffix) : $size;
            } else {
                $start = (int) $parts[1];
                $end = $parts[2] === '' ? $end : min($end, (int) $parts[2]);
            }
            if ($start > $end || $start >= $size) {
                fclose($handle);

                return $this->error('invalid_range', 416)->header('Content-Range', 'bytes */'.$size);
            }
            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }
        $length = max(0, $end - $start + 1);
        $headers['Content-Length'] = (string) $length;
        if ($request->isMethod('HEAD')) {
            fclose($handle);

            return response('', 200, $headers);
        }

        return response()->stream(function () use ($handle, $start, $length) {
            try {
                fseek($handle, $start);
                $remaining = $length;
                while ($remaining > 0 && ! feof($handle)) {
                    $chunk = fread($handle, min(1024 * 1024, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
            } finally {
                fclose($handle);
            }
        }, $status, $headers);
    }
}
