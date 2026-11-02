<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'code', 'email', 'phone', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function deviceGroups(): HasMany { return $this->hasMany(DeviceGroup::class); }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
