<?php

use App\Http\Controllers\Api\AccessCheckController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('events/{event}/access-check', AccessCheckController::class)->middleware('throttle:venue-ip');
});
