<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('weather', WeatherController::class)->middleware('throttle:60,1');

Route::apiResource('posts', PostController::class)->only(['index', 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::apiResource('posts', PostController::class)->only(['store', 'update', 'destroy']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
});
