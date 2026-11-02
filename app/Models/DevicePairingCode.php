<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePairingCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => UtcDateTime::class, 'consumed_at' => UtcDateTime::class, 'cancelled_at' => UtcDateTime::class, 'attempts' => 'integer'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
