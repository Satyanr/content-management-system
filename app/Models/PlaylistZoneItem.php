<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistZoneItem extends Model
{
    protected $fillable = [
        'playlist_zone_id', 'media_asset_id', 'sort_order',
        'duration_seconds', 'settings', 'max_plays',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer', 'max_plays' => 'integer',
            'duration_seconds' => 'integer',
            'settings' => 'array',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(PlaylistZone::class, 'playlist_zone_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
