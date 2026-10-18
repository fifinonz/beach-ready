<?php

use App\Http\Controllers\Email\ConfirmEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Links in emails. They work without logging in, because the signature
// proves the link came from us; see decision 0006.
Route::get('/email/confirm/{user}/{hash}', ConfirmEmailController::class)
    ->middleware('signed')
    ->name('email.confirm');
