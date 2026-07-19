<?php

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FactorySelectionController;
use App\Http\Controllers\Web\CameraController;
use App\Http\Controllers\Web\ZoneController;
use App\Http\Controllers\Web\FireEventController;
use App\Http\Controllers\Web\AlertController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\FactorySettingController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\FactoryController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\CameraHealthController;
use App\Http\Controllers\Web\AlertRecipientController;
use App\Http\Controllers\Web\ApiClientController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth')->name('login.store');
});

Route::middleware(['auth', 'verified', 'web.context'])->group(function (): void {
    Route::post('/factories/{factory}/select', FactorySelectionController::class)->name('factory.switch');
    Route::resource('factories', FactoryController::class);
    Route::post('factories/{factory}/toggle', [FactoryController::class, 'toggle'])->name('factories.toggle');
    Route::resource('users', UserController::class)->only(['index','show','edit','update','destroy']);
    Route::resource('roles', RoleController::class)->only(['index','show','edit','update']);
    Route::resource('plans', PlanController::class)->except(['show','destroy']);
    Route::post('plans/{plan}/toggle', [PlanController::class,'toggle'])->name('plans.toggle');
    Route::resource('subscriptions', SubscriptionController::class)->except(['destroy']);
    Route::get('profile', [ProfileController::class,'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class,'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class,'password'])->name('profile.password');

    Route::middleware('factory.web')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('members', MemberController::class)->except(['show']);
        Route::get('camera-health', [CameraHealthController::class,'index'])->name('camera-health.index');
        Route::get('camera-health/{camera}', [CameraHealthController::class,'show'])->name('camera-health.show');
        Route::resource('recipients', AlertRecipientController::class)->except(['show']);
        Route::resource('api-clients', ApiClientController::class)->only(['index','create','store','show']);
        Route::post('api-clients/{api_client}/toggle', [ApiClientController::class,'toggle'])->name('api-clients.toggle');
        Route::post('api-clients/{api_client}/regenerate-secret', [ApiClientController::class,'regenerate'])->name('api-clients.regenerate-secret');
        Route::post('api-clients/{api_client}/revoke', [ApiClientController::class,'revoke'])->name('api-clients.revoke');
        Route::resource('zones', ZoneController::class)->except(['show']);
        Route::resource('cameras', CameraController::class)->except(['show']);
        Route::resource('events', FireEventController::class)->only(['index','show']);
        Route::post('events/{event}/confirm', [FireEventController::class,'confirm'])->name('events.confirm');
        Route::post('events/{event}/false-alarm', [FireEventController::class,'falseAlarm'])->name('events.false-alarm');
        Route::post('events/{event}/resolve', [FireEventController::class,'resolve'])->name('events.resolve');
        Route::resource('alerts', AlertController::class)->only(['index','show']);
        Route::post('alerts/{alert}/acknowledge', [AlertController::class,'acknowledge'])->name('alerts.acknowledge');
        Route::resource('notifications', NotificationController::class)->only(['index','show']);
        Route::get('settings', [FactorySettingController::class,'edit'])->name('settings.edit');
        Route::put('settings', [FactorySettingController::class,'update'])->name('settings.update');
        Route::get('audit-logs', [AuditLogController::class,'index'])->name('audit-logs.index');
        Route::get('reports', [ReportController::class,'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class,'export'])->name('reports.export');
    });
});

Route::middleware(['auth','web.context'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('verify-email', [EmailVerificationController::class,'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class,'verify'])->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class,'send'])->middleware('throttle:6,1')->name('verification.send');
});
