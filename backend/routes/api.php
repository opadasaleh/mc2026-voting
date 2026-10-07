<?php

use App\Http\Controllers\Api\AccessCheckController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Api\VoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:venue-ip')->group(function () {
    Route::get('events', [EventController::class, 'index']);

    Route::prefix('events/{event}')->group(function () {
        Route::get('/', [EventController::class, 'show']);
        Route::get('categories', [EventController::class, 'categories']);
        Route::get('access-check', AccessCheckController::class);

        Route::middleware('on-site')->group(function () {
            Route::post('auth/otp/request', [OtpController::class, 'request']);
            Route::post('auth/otp/verify', [OtpController::class, 'verify']);
        });

        Route::middleware('visitor')->group(function () {
            Route::get('me', [VisitorController::class, 'me']);
            Route::post('auth/logout', [VisitorController::class, 'logout']);
            // Token first, then the Wi-Fi gate (middleware priority in bootstrap/app.php).
            Route::post('votes', VoteController::class)->middleware(['on-site', 'throttle:votes']);
        });
    });
});
