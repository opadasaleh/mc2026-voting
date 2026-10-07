<?php

use App\Http\Controllers\Api\AccessCheckController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\VisitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/events/{event}')->middleware('throttle:venue-ip')->group(function () {
    Route::get('access-check', AccessCheckController::class);

    Route::middleware('on-site')->group(function () {
        Route::post('auth/otp/request', [OtpController::class, 'request']);
        Route::post('auth/otp/verify', [OtpController::class, 'verify']);
    });

    Route::middleware('visitor')->group(function () {
        Route::get('me', [VisitorController::class, 'me']);
        Route::post('auth/logout', [VisitorController::class, 'logout']);
    });
});
