<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    protected $fillable = ['company_id', 'uploaded_by', 'title', 'original_name', 'file_name', 'disk', 'path', 'mime_type', 'extension', 'type', 'size', 'width', 'height', 'duration', 'metadata', 'is_active'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . ltrim($this->path, '/'));
    }

    public function getSizeFormattedAttribute(): string
    {
        if ($this->size >= 1073741824) {
            return number_format($this->size / 1073741824, 2) . ' GB';
        }

        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 2) . ' MB';
        }

        if ($this->size >= 1024) {
            return number_format($this->size / 1024, 2) . ' KB';
        }

        return $this->size . ' B';
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        if ($companyId === null) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }
}
