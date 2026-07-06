<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Core\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService extends BaseService
{
    public function log(
        string $action,
        string $module,
        ?string $description = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        $user = Auth::user();

        return ActivityLog::query()->create([
            'company_id' => $user?->company_id,
            'user_id' => $user?->id,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}