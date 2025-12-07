<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Gateway Service API endpoints for the microservices system.
|
*/

// Login - proxies to Auth Service (no token validation needed)
Route::post('/login', [GatewayController::class, 'login']);

// Register - proxies to Auth Service (no token validation needed)
Route::post('/register', [GatewayController::class, 'register']);

// Upload - requires valid token with admin privileges
Route::post('/upload', [GatewayController::class, 'upload'])
    ->middleware('auth.token:true');

// Download - requires valid token with admin privileges
Route::get('/download', [GatewayController::class, 'download'])
    ->middleware('auth.token:true');
