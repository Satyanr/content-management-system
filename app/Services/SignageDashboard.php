<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Device;
use App\Models\Playlist;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class SignageDashboard
{
    public function data(): array
    {
        $workspace = app(WorkspaceService::class);
        $companyId = $workspace->companyId();
        $all = $workspace->isAllCompanies();
        // Null only grants global reads for an authenticated super-admin in All Companies.
        $scope = fn (Builder $query) => $companyId !== null ? $query->where('company_id', $companyId) : ($all ? $query : $query->whereRaw('1 = 0'));
        $now = CarbonImmutable::now('UTC');
        $data = ['workspaceName' => $workspace->company()?->name ?? ($all ? 'Semua Perusahaan' : 'Workspace belum tersedia'), 'allCompanies' => $all,
            'deviceStats' => null, 'recentDevices' => collect(), 'activePlaylists' => null, 'scheduleStats' => null,
            'scheduled' => null, 'scheduledWindow' => null, 'transition' => null, 'latestActivities' => collect(), 'asOf' => $now];
        if (Gate::allows('devices.view')) {
            $devices = $scope(Device::query())->withCount('activeCredentials');
            $stats = ['total' => 0, 'online' => 0, 'offline' => 0, 'pending' => 0, 'other' => 0];
            foreach ((clone $devices)->cursor() as $device) {
                $stats['total']++;
                $key = match ($device->connectionStatus()) {
                    'Online' => 'online', 'Offline', 'Never connected' => 'offline', 'Pending' => 'pending', default => 'other'
                };
                $stats[$key]++;
            }
            $data['deviceStats'] = $stats;
            $data['recentDevices'] = $devices->orderByDesc('last_seen_at')->orderBy('id')->limit(6)->get();
        }
        if (Gate::allows('playlists.view')) {
            $data['activePlaylists'] = $scope(Playlist::query())->where('is_active', true)->count();
        }
        if (Gate::allows('scheduler.view') && Gate::allows('playlists.view')) {
            $resolver = app(ScheduleResolver::class);
            $clock = app(ScheduleClock::class);
            $companies = $all ? Company::query()->where('is_active', true)->pluck('id') : collect($companyId ? [$companyId] : []);
            $stats = ['active' => 0, 'upcoming' => 0];
            foreach ($companies as $id) {
                foreach ($resolver->eligible($id) as $schedule) {
                    if ($clock->activeAt($schedule, $now)) {
                        $stats['active']++;
                    } elseif (collect($clock->occurrences($schedule, $now, $now->addDays(7)))->contains(fn ($interval) => $interval['start']->gt($now))) {
                        $stats['upcoming']++;
                    }
                }
            }
            $data['scheduleStats'] = $stats;
            if ($companyId !== null) {
                $data['scheduled'] = $resolver->resolve($companyId, $now);
                if ($data['scheduled']) {
                    $data['scheduledWindow'] = collect($clock->occurrences($data['scheduled'], $now, $now->addSecond()))->first();
                }
                // A bounded planning horizon, never claim its end is a real transition.
                $segments = $resolver->timeline($companyId, $now, $now->addDays(7));
                if (count($segments) > 1) {
                    $data['transition'] = $segments[0]['end'];
                }
            }
        }
        if (Gate::allows('activity_logs.view')) {
            $data['latestActivities'] = $scope(ActivityLog::query())->with('user')->latest()->limit(5)->get();
        }

        return $data;
    }
}
