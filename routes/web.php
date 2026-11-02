<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MediaAssetController;
use App\Http\Controllers\Admin\MediaStreamController;
use App\Http\Controllers\Admin\MediaUploadController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlaylistController;
use App\Http\Controllers\Admin\ProofOfPlayController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchedulerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Services\PlaylistWorkspace;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'cms.maintenance', 'permission:dashboard.view', 'login.activity', 'company.active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/proof-of-play', [ProofOfPlayController::class, 'index'])->middleware('permission:playback.view')->name('proof-of-play.index');
        Route::get('/proof-of-play/export', [ProofOfPlayController::class, 'export'])->middleware(['permission:playback.view', 'permission:playback.export'])->name('proof-of-play.export');
        Route::post('/proof-of-play/reset', [ProofOfPlayController::class, 'reset'])->middleware(['permission:playback.view', 'permission:playback.reset'])->name('proof-of-play.reset');
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
        Route::get('/playlists/{playlist}/assignment', function (int $playlist) {
            app(PlaylistWorkspace::class)->find($playlist);

            return view('admin.playlists.assignment', ['playlistId' => $playlist]);
        })->whereNumber('playlist')->middleware(['permission:playlists.view', 'permission:devices.view'])->name('playlists.assignment');
        Route::get('/playlists/{playlist}/studio', [PlaylistController::class, 'studio'])
            ->whereNumber('playlist')
            ->middleware('permission:playlists.view')
            ->name('playlists.studio');
        Route::get('/devices', [DeviceController::class, 'index'])->middleware('permission:devices.view')->name('devices.index');
        Route::get('/devices/{publicId}', [DeviceController::class, 'show'])->whereUuid('publicId')->middleware('permission:devices.view')->name('devices.show');
        Route::get('/scheduler', [SchedulerController::class, 'index'])
            ->middleware('permission:scheduler.view')->name('scheduler.index');
        Route::get('/playlists', [PlaylistController::class, 'index'])
            ->middleware('permission:playlists.view')
            ->name('playlists.index');
        Route::post('/workspace/change', [WorkspaceController::class, 'change'])->name('workspace.change');
        Route::get('/media-assets', [MediaAssetController::class, 'index'])
            ->middleware('permission:media.view')
            ->name('media-assets.index');
        Route::post('/media-assets/upload', [MediaUploadController::class, 'store'])
            ->middleware('permission:media.create')
            ->name('media-assets.upload');
        Route::get('/media-assets/{media}/stream', [MediaStreamController::class, 'show'])
            ->middleware('permission:media.view')
            ->name('media-assets.stream');
    });

require __DIR__.'/auth.php';
