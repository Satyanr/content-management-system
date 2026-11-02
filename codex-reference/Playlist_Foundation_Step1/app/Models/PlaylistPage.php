<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaylistPage extends Model
{
    protected $fillable = ['playlist_id', 'name', 'sort_order', 'duration_seconds', 'background_color'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'duration_seconds' => 'integer'];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(PlaylistZone::class)->orderBy('z_index');
    }
}
