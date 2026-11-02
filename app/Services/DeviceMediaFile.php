<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeviceMediaFile
{
    /** Open one immutable file handle; hash in chunks, never load a video into memory. */
    public function open(MediaAsset $asset): ?array
    {
        $handle = null;
        try {
            if (! in_array($asset->disk, ['local', 'public'], true) || ! $asset->is_active || ! in_array($asset->type, ['image', 'video'], true)) {
                return null;
            }
            $disk = Storage::disk($asset->disk);
            $root = realpath($disk->path(''));
            $path = realpath($disk->path($asset->path));
            $normalize = fn ($value) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $value)) : str_replace('\\', '/', $value);
            if (! $root || ! $path || ! str_starts_with($normalize($path), rtrim($normalize($root), '/').'/') || ! is_file($path)) {
                return null;
            }
            $handle = @fopen($path, 'rb');
            if (! $handle) {
                return null;
            }
            $hash = hash_init('sha256');
            $size = hash_update_stream($hash, $handle);
            if ($size === false || ! rewind($handle)) {
                fclose($handle);

                return null;
            }

            return ['handle' => $handle, 'size' => $size, 'sha256' => hash_final($hash)];
        } catch (Throwable) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return null;
        }
    }

    public function metadata(MediaAsset $asset): array
    {
        $file = $this->open($asset);
        if ($file) {
            fclose($file['handle']);
        }
        $mime = $asset->mime_type;
        $mime = is_string($mime) && preg_match('#^(?:image|video)/[a-zA-Z0-9.+-]+$#D', $mime) ? $mime : 'application/octet-stream';

        return ['id' => $asset->id, 'type' => $asset->type, 'mimeType' => $mime,
            'size' => $file ? $file['size'] : null, 'checksum' => $file ? ['algorithm' => 'sha256', 'value' => $file['sha256']] : null,
            'available' => $file !== null, 'downloadUrl' => route('device.media', ['media' => $asset->id], false)];
    }
}
