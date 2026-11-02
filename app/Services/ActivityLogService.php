<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Models\ActivityLog;
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
        ?array $newValues = null,
        ?int $companyId = null,
        bool $systemActor = false
    ): ActivityLog {
        $user = $systemActor ? null : Auth::user();

        return ActivityLog::query()->create([
            'company_id' => $companyId ?? $user?->company_id,
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
