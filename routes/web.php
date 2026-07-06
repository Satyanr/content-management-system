<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Admin\MediaAssetController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'cms.maintenance', 'permission:dashboard.view', 'login.activity', 'company.active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::view('/profile', 'profile')->name('profile');
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('users.index');
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('roles.index');
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permissions.view')
            ->name('permissions.index');
        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:menus.view')
            ->name('menus.index');
        Route::get('/companies', [CompanyController::class, 'index'])
            ->middleware('permission:companies.view')
            ->name('companies.index');
        Route::get('/settings', [SettingController::class, 'index'])
            ->middleware('permission:settings.view')
            ->name('settings.index');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('permission:activity_logs.view')
            ->name('activity-logs.index');
        Route::get('/login-histories', [LoginHistoryController::class, 'index'])
            ->middleware('permission:login_histories.view')
            ->name('login-histories.index');
        Route::post('/workspace/change', [WorkspaceController::class, 'change'])->name('workspace.change');
        Route::get('/media-assets', [MediaAssetController::class, 'index'])
            ->middleware('permission:media.view')
            ->name('media-assets.index');
    });

require __DIR__ . '/auth.php';
