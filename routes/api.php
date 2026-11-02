<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('device')->middleware('device.payload')->group(function () {
    Route::post('/activate', [DeviceController::class, 'activate'])->middleware('throttle:device-activate')->name('device.activate');
    Route::post('/heartbeat', [DeviceController::class, 'heartbeat'])->middleware(['throttle:device-ingress', 'device.auth'])->name('device.heartbeat');
});
