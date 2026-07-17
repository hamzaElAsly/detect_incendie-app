<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FactoryContextController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::prefix('auth')->middleware(['auth.api', 'throttle:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth.api', 'factory', 'throttle:api'])->group(function () {
    Route::get('factory-context', [FactoryContextController::class, 'show']);
});
