<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\CompanyController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])
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
            ->middleware('permission:roles.view')
            ->name('permissions.index');
        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:settings.view')
            ->name('menus.index');
        Route::get('/companies', [CompanyController::class, 'index'])
            ->middleware('permission:settings.view')
            ->name('companies.index');
    });

require __DIR__ . '/auth.php';
