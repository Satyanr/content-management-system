<?php

namespace App\Services;

class MediaTransform
{
    public static function defaults(string $fit = 'contain'): array
    {
        return ['scale' => 1, 'offset_x' => 0, 'offset_y' => 0, 'fit_mode' => in_array($fit, ['contain', 'cover', 'stretch'], true) ? $fit : 'contain', 'keep_aspect_ratio' => $fit !== 'stretch'];
    }

    /** Normalize legacy JSON defensively; never serialize editor request IDs or arbitrary settings. */
    public static function read(?array $settings, string $fit = 'contain'): array
    {
        $value = $settings['media_transform'] ?? [];
        $result = self::defaults($fit);
        if (! is_array($value)) {
            return $result;
        }
        foreach (['scale' => [1, 5], 'offset_x' => [-100, 100], 'offset_y' => [-100, 100]] as $key => [$min, $max]) {
            if (is_numeric($value[$key] ?? null) && is_finite((float) $value[$key])) {
                $result[$key] = max($min, min($max, (float) $value[$key]));
            }
        }
        if (in_array($value['fit_mode'] ?? null, ['contain', 'cover', 'stretch'], true)) {
            $result['fit_mode'] = $value['fit_mode'];
        }
        if (is_bool($value['keep_aspect_ratio'] ?? null)) {
            $result['keep_aspect_ratio'] = $value['keep_aspect_ratio'];
        }
        if ($result['fit_mode'] === 'stretch') {
            $result['keep_aspect_ratio'] = false;
        }

        return $result;
    }

    public static function validate(array $input): array
    {
        return validator(['transform' => $input], ['transform' => ['required', 'array:scale,offset_x,offset_y,fit_mode,keep_aspect_ratio'],
            'transform.scale' => ['required', 'numeric', 'between:1,5'], 'transform.offset_x' => ['required', 'numeric', 'between:-100,100'], 'transform.offset_y' => ['required', 'numeric', 'between:-100,100'],
            'transform.fit_mode' => ['required', 'in:contain,cover,stretch'], 'transform.keep_aspect_ratio' => ['required', 'boolean']])->validate()['transform'];
    }
}
