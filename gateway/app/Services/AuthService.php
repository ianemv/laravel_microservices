<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthService
{
    protected string $authServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = 'http://' . config('services.auth.address');
    }

    /**
     * Proxy register request to Auth Service.
     * Accepts JSON body with email/password.
     *
     * @return array{token: string|null, message: string|null, error: string|null, status: int}
     */
    public function register(Request $request): array
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return [
                'token' => null,
                'message' => null,
                'error' => 'email and password are required',
                'status' => 400,
            ];
        }

        try {
            $response = Http::post("{$this->authServiceUrl}/api/register", [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'token' => $data['token'] ?? null,
                    'message' => $data['message'] ?? 'user created successfully',
                    'error' => null,
                    'status' => 201,
                ];
            }

            $errorData = $response->json();
            return [
                'token' => null,
                'message' => null,
                'error' => $errorData['error'] ?? 'registration failed',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'token' => null,
                'message' => null,
                'error' => 'auth service unavailable',
                'status' => 500,
            ];
        }
    }

    /**
     * Proxy login request to Auth Service.
     * Accepts JSON body with email/password, converts to Basic Auth for auth service.
     *
     * @return array{token: string|null, error: string|null, status: int}
     */
    public function login(Request $request): array
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return [
                'token' => null,
                'error' => 'missing credentials',
                'status' => 401,
            ];
        }

        $basicAuth = base64_encode("{$email}:{$password}");

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$basicAuth}",
            ])->post("{$this->authServiceUrl}/api/login");

            if ($response->successful()) {
                return [
                    'token' => $response->body(),
                    'error' => null,
                    'status' => 200,
                ];
            }

            return [
                'token' => null,
                'error' => 'invalid credentials',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'token' => null,
                'error' => 'auth service unavailable',
                'status' => 500,
            ];
        }
    }

    /**
     * Validate JWT token via Auth Service.
     *
     * @return array{data: array|null, error: string|null, status: int}
     */
    public function validateToken(Request $request): array
    {
        $authorization = $request->header('Authorization');

        if (!$authorization) {
            return [
                'data' => null,
                'error' => 'not authorized',
                'status' => 401,
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $authorization,
            ])->post("{$this->authServiceUrl}/api/validate");

            if ($response->successful()) {
                return [
                    'data' => $response->json(),
                    'error' => null,
                    'status' => 200,
                ];
            }

            return [
                'data' => null,
                'error' => 'not authorized',
                'status' => 401,
            ];
        } catch (\Exception $e) {
            return [
                'data' => null,
                'error' => 'auth service unavailable',
                'status' => 500,
            ];
        }
    }

    /**
     * Check if user has admin privileges.
     */
    public function isAdmin(array $tokenData): bool
    {
        return isset($tokenData['admin']) && $tokenData['admin'] === true;
    }
}
