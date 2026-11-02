<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $fillable = ['company_id', 'name', 'description', 'platform', 'location', 'screen_width', 'screen_height', 'orientation', 'created_by'];

    protected function casts(): array
    {
        return ['screen_width' => 'integer', 'screen_height' => 'integer', 'last_seen_at' => UtcDateTime::class, 'activated_at' => UtcDateTime::class, 'heartbeat' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Device $device) {
            $device->public_id ??= (string) Str::uuid();
            $device->status ??= 'pending';
        });
    }

    public function scopeForCompany(Builder $query, ?int $id): Builder
    {
        return $id === null ? $query->whereRaw('1 = 0') : $query->where('devices.company_id', $id);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(DeviceGroup::class, 'device_group_device')->withPivot('company_id');
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(DevicePairingCode::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DeviceCredential::class);
    }

    public function activeCredentials(): HasMany
    {
        return $this->credentials()->whereNull('revoked_at');
    }

    public function connectionStatus(): string
    {
        if ($this->status === 'disabled') {
            return 'Disabled';
        }
        if ($this->status === 'pending') {
            return 'Pending';
        }
        $count = $this->active_credentials_count ?? $this->activeCredentials()->count();
        if (! $count) {
            return 'Revoked';
        }
        if (! $this->last_seen_at) {
            return 'Never connected';
        }
        $now = CarbonImmutable::now('UTC');

        return $this->last_seen_at->lte($now) && $this->last_seen_at->gte($now->subSeconds(max(1, (int) config('devices.heartbeat_freshness_seconds', 180)))) ? 'Online' : 'Offline';
    }
}
