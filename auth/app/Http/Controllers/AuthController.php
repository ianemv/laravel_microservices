<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register endpoint - creates a new user with admin role.
     */
    public function register(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json(['error' => 'email and password are required'], 400);
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            return response()->json(['error' => 'user already exists'], 409);
        }

        // Create user with hashed password
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // Generate token for immediate use
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'user created successfully',
            'token' => $token,
        ], 201);
    }

    /**
     * Login endpoint - authenticates user with Basic Auth and returns Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        // Get Basic Auth credentials from Authorization header
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return response()->json('missing credentials', 401);
        }

        // Decode Basic Auth credentials
        $credentials = base64_decode(substr($authHeader, 6));
        $parts = explode(':', $credentials, 2);

        if (count($parts) !== 2) {
            return response()->json('invalid credentials', 401);
        }

        [$email, $password] = $parts;

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json('invalid credentials', 401);
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            return response()->json('invalid credentials', 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json($token, 200);
    }

    /**
     * Validate endpoint - validates Sanctum token and returns user info.
     */
    public function validateToken(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return response()->json('missing credentials', 401);
        }

        // Extract token from Bearer header
        $token = null;
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        } else {
            $token = $authHeader;
        }

        if (!$token) {
            return response()->json('missing credentials', 401);
        }

        // Find token in database
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json('not authorized', 403);
        }

        // Check if token is expired (if expiration is set)
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json('not authorized', 403);
        }

        $user = $accessToken->tokenable;

        if (!$user) {
            return response()->json('not authorized', 403);
        }

        // Return user info matching Python service format (admin: true for now)
        return response()->json([
            'username' => $user->email,
            'exp' => $accessToken->expires_at ? $accessToken->expires_at->timestamp : null,
            'iat' => $accessToken->created_at->timestamp,
            'admin' => true,
            'sub' => $user->id,
        ], 200);
    }
}
