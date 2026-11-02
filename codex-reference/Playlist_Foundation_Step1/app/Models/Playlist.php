<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    protected $fillable = [
        'company_id', 'created_by', 'name', 'description',
        'canvas_width', 'canvas_height', 'background_color', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PlaylistPage::class)->orderBy('sort_order');
    }

    // A missing workspace must NEVER mean "all companies" for Playlist operations.
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $companyId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('company_id', $companyId);
    }
}
