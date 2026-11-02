<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaylistZone extends Model
{
    protected $fillable = [
        'playlist_page_id', 'name', 'x', 'y', 'width', 'height', 'z_index',
        'keep_aspect_ratio', 'fit_mode', 'is_visible', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer', 'y' => 'integer',
            'width' => 'integer', 'height' => 'integer', 'z_index' => 'integer',
            'keep_aspect_ratio' => 'boolean', 'is_visible' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PlaylistPage::class, 'playlist_page_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistZoneItem::class)->orderBy('sort_order');
    }
}
