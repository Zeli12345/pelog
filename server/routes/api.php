<?php

use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\DeviceEnrollController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ScreenshotController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentPinController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Middleware\EnsureDeviceToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Publik (rate limit ketat untuk enroll)
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/version', [HealthController::class, 'version']);
    Route::post('/devices/enroll', [DeviceEnrollController::class, 'store'])->middleware('throttle:10,1');

    // Perangkat terautentikasi
    Route::middleware([EnsureDeviceToken::class, 'throttle:device'])->group(function () {
        Route::get('/bootstrap', [BootstrapController::class, 'index']);

        Route::get('/app/latest', [AppUpdateController::class, 'latest']);
        Route::get('/app/installer', [AppUpdateController::class, 'download']);

        Route::get('/students/{nisn}', [StudentController::class, 'show']);
        Route::post('/students/{nisn}/pin', [StudentPinController::class, 'store']);

        Route::post('/sessions/start', [SessionController::class, 'start']);
        Route::post('/sessions/heartbeat', [SessionController::class, 'heartbeat']);
        Route::post('/sessions/end', [SessionController::class, 'end']);

        Route::post('/sessions/{sessionUuid}/screenshot', [ScreenshotController::class, 'store']);

        Route::post('/sync/sessions', [SyncController::class, 'sessions']);
    });
});
