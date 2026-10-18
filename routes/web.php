<?php

use App\Http\Controllers\Email\ConfirmEmailController;
use App\Http\Controllers\Email\PreferenceCenterController;
use App\Http\Controllers\Email\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Links in emails. They work without logging in, because the signature
// proves the link came from us; see decision 0006.
Route::middleware('signed')->prefix('email')->group(function () {
    Route::get('/confirm/{user}/{hash}', ConfirmEmailController::class)->name('email.confirm');

    Route::get('/preferences/{user}', [PreferenceCenterController::class, 'show'])->name('email.preferences');
    Route::post('/preferences/{user}', [PreferenceCenterController::class, 'update']);

    Route::get('/unsubscribe/{user}', [UnsubscribeController::class, 'show'])->name('email.unsubscribe');
    Route::post('/unsubscribe/{user}', [UnsubscribeController::class, 'store']);
});
