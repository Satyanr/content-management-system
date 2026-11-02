<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeviceGroup extends Model
{
    protected $fillable = ['company_id', 'name', 'description'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_group_device')->withPivot('company_id');
    }

    public function scopeForCompany(Builder $query, ?int $id): Builder
    {
        return $id === null ? $query->whereRaw('1 = 0') : $query->where('device_groups.company_id', $id);
    }
}
