<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = ['company_id', 'playlist_id', 'created_by', 'name', 'schedule_type', 'timezone', 'starts_at_utc', 'ends_at_utc', 'start_date', 'end_date', 'start_time', 'end_time', 'weekdays', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at_utc' => UtcDateTime::class, 'ends_at_utc' => UtcDateTime::class, 'weekdays' => 'array', 'priority' => 'integer', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCompany(Builder $query, ?int $id): Builder
    {
        return $id === null ? $query->whereRaw('1 = 0') : $query->where('company_id', $id)->whereHas('playlist', fn ($q) => $q->where('company_id', $id));
    }
}
