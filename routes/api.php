<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index'])->name('api.health');

Route::middleware('auth')->group(function () {
    Route::get('/user', [UserController::class, 'show'])->name('api.user');
});
