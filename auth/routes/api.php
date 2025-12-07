<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Auth service endpoints matching Python mp3converter auth service:
| POST /register - Register new user with email/password
| POST /login    - Authenticate with Basic Auth, returns token
| POST /validate - Validate token, returns decoded claims
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/validate', [AuthController::class, 'validateToken']);

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'healthy'], 200);
});
