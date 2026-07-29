<?php

use App\Domain\Audit\AuditLogController;
use App\Domain\Dashboard\DashboardController;
use App\Domain\Health\HealthController;
use App\Domain\Media\IncidentMediaController;
use App\Domain\Settings\FactorySettingsController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);

Route::middleware(['auth.api', 'throttle:api'])->prefix('factories/{factory}')->group(function () {

    Route::middleware('factory')->prefix('dashboard')->group(function () {
        Route::get('summary', [DashboardController::class, 'summary']);
        Route::get('events-chart', [DashboardController::class, 'eventsChart']);
        Route::get('camera-status', [DashboardController::class, 'cameraStatus']);
        Route::get('recent-events', [DashboardController::class, 'recentEvents']);
        Route::get('metrics', [DashboardController::class, 'metrics']);
    });

    Route::middleware('factory')->group(function () {
        Route::get('settings', [FactorySettingsController::class, 'show']);
        Route::patch('settings', [FactorySettingsController::class, 'update']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::post('events/{event}/media', [IncidentMediaController::class, 'store']);
    });
});
