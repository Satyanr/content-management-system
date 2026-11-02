<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCredential extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'token_identifier'];

    protected function casts(): array
    {
        return ['last_used_at' => UtcDateTime::class, 'revoked_at' => UtcDateTime::class];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
