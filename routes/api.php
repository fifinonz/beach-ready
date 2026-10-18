<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Fasts\ActiveFastController;
use App\Http\Controllers\Fasts\AllTimeStatsController;
use App\Http\Controllers\Fasts\FastController;
use App\Http\Controllers\Fasts\WeeklyStatsController;
use App\Http\Controllers\Settings\EmailSettingsController;
use App\Http\Controllers\Settings\ReminderSettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['throttle:register', 'honeypot']);
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', [ProfileController::class, 'show']);
    Route::patch('/user', [ProfileController::class, 'update']);

    Route::get('/fasts', [FastController::class, 'index']);
    Route::post('/fasts', [FastController::class, 'store']);
    Route::get('/fasts/active', [ActiveFastController::class, 'show']);
    Route::patch('/fasts/active', [ActiveFastController::class, 'update']);
    Route::post('/fasts/active/end', [ActiveFastController::class, 'end']);
    Route::get('/stats/week', WeeklyStatsController::class);
    Route::get('/stats/all-time', AllTimeStatsController::class);

    Route::get('/settings/reminders', [ReminderSettingsController::class, 'show']);
    Route::patch('/settings/reminders', [ReminderSettingsController::class, 'update']);

    Route::get('/settings/email', [EmailSettingsController::class, 'show']);
    Route::patch('/settings/email', [EmailSettingsController::class, 'update']);
    Route::post('/settings/email/confirmation', [EmailSettingsController::class, 'resendConfirmation'])
        ->middleware('throttle:confirmation-resend');

    Route::get('/account/export', [AccountController::class, 'export']);
    Route::delete('/account', [AccountController::class, 'destroy'])
        ->middleware('throttle:account-delete');
});
