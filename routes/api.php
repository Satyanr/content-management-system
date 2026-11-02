<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\PlaybackEventController;
use App\Http\Controllers\Api\PlayerController;
use Illuminate\Support\Facades\Route;

Route::prefix('device')->middleware(['device.context', 'device.payload'])->group(function () {
    Route::post('/activate', [DeviceController::class, 'activate'])->middleware('throttle:device-activate')->name('device.activate');
    Route::post('/playback-events', [PlaybackEventController::class, 'store'])->middleware(['throttle:device-player-ingress', 'device.auth', 'device.limit:events'])->name('device.playback-events');
    Route::post('/playback-state', [PlaybackEventController::class, 'state'])->middleware(['throttle:device-player-ingress', 'device.auth', 'device.limit:events'])->name('device.playback-state');
    Route::post('/heartbeat', [DeviceController::class, 'heartbeat'])->middleware(['throttle:device-ingress', 'device.auth', 'device.limit:heartbeat'])->name('device.heartbeat');
    Route::get('/manifest', [PlayerController::class, 'manifest'])->middleware(['throttle:device-player-ingress', 'device.auth', 'device.limit:manifest'])->name('device.manifest');
    Route::match(['GET', 'HEAD'], '/media/{media}', [PlayerController::class, 'media'])->middleware(['throttle:device-player-ingress', 'device.auth', 'device.limit:media'])->name('device.media');
});
