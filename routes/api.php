<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Fasts\ActiveFastController;
use App\Http\Controllers\Fasts\FastController;
use App\Http\Controllers\Fasts\WeeklyStatsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()));

    Route::get('/fasts', [FastController::class, 'index']);
    Route::post('/fasts', [FastController::class, 'store']);
    Route::get('/fasts/active', [ActiveFastController::class, 'show']);
    Route::patch('/fasts/active', [ActiveFastController::class, 'update']);
    Route::post('/fasts/active/end', [ActiveFastController::class, 'end']);
    Route::get('/stats/week', WeeklyStatsController::class);
});
