<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\WorkspaceService;

class DashboardController extends Controller
{
    public function index()
    {
        $workspaceCompanyId = app(WorkspaceService::class)->companyId();

        $sessionLifetime = (int) config('session.lifetime', 120);
        $activeLimit = now()->subMinutes($sessionLifetime);

        $usersQuery = User::query();

        if ($workspaceCompanyId !== null) {
            $usersQuery->where('company_id', '=', $workspaceCompanyId);
        }

        $activityLogsQuery = ActivityLog::query();

        if ($workspaceCompanyId !== null) {
            $activityLogsQuery->where('company_id', '=', $workspaceCompanyId);
        }

        $loginHistoriesQuery = LoginHistory::query();

        if ($workspaceCompanyId !== null) {
            $loginHistoriesQuery->where('company_id', '=', $workspaceCompanyId);
        }

        $totalCompanies = Company::query()->count('*');

        $totalUsers = (clone $usersQuery)->count('*');

        $totalActivityLogs = (clone $activityLogsQuery)->count('*');

        $onlineUsers = (clone $loginHistoriesQuery)->where('logout_at', '=', null)->where('last_activity_at', '>=', $activeLimit)->count('*');

        $inactiveSessions = (clone $loginHistoriesQuery)
            ->where('logout_at', '=', null)
            ->where(function ($query) use ($activeLimit) {
                $query->where('last_activity_at', '<', $activeLimit)->orWhere('last_activity_at', '=', null);
            })
            ->count('*');

        $latestActivities = (clone $activityLogsQuery)
            ->with(['user', 'company'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        $latestLogins = (clone $loginHistoriesQuery)
            ->with(['user', 'company'])
            ->orderBy('login_at', 'desc')
            ->limit(8)
            ->get();

        return view('admin.dashboard.index', [
            'workspaceCompanyId' => $workspaceCompanyId,
            'totalCompanies' => $totalCompanies,
            'totalUsers' => $totalUsers,
            'totalActivityLogs' => $totalActivityLogs,
            'onlineUsers' => $onlineUsers,
            'inactiveSessions' => $inactiveSessions,
            'latestActivities' => $latestActivities,
            'latestLogins' => $latestLogins,
        ]);
    }
}
