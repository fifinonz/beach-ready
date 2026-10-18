<?php

use App\Http\Controllers\Email\ConfirmEmailController;
use App\Http\Controllers\Email\PreferenceCenterController;
use App\Http\Controllers\Email\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Links in emails. They work without logging in, because the signature
// proves the link came from us; see decision 0006.
Route::prefix('email')->group(function () {
    Route::middleware('signed')->group(function () {
        Route::get('/confirm/{user}/{hash}', [ConfirmEmailController::class, 'show'])->name('email.confirm');
        Route::post('/confirm/{user}/{hash}', [ConfirmEmailController::class, 'store']);

        // Unsubscribe is deliberately not rate limited: mail providers send
        // one-click unsubscribes for many people from the same servers, and
        // it's signed and harmless to repeat. See decision 0007.
        Route::get('/unsubscribe/{user}', [UnsubscribeController::class, 'show'])->name('email.unsubscribe');
        Route::post('/unsubscribe/{user}', [UnsubscribeController::class, 'store']);
    });

    // Requests with made-up signatures count towards the rate limit too:
    // Laravel's middleware priority runs throttling before the signature
    // check, and a test makes sure it stays that way.
    Route::middleware(['throttle:preference-center', 'signed'])->group(function () {
        Route::get('/preferences/{user}', [PreferenceCenterController::class, 'show'])->name('email.preferences');
        Route::post('/preferences/{user}', [PreferenceCenterController::class, 'update']);
    });
});

// Every other path is a screen in the Vue app, which does its own routing.
// API, email-link, Sanctum and health-check paths are left to their routes.
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api/|email/|sanctum/|up$|build/).*$');
