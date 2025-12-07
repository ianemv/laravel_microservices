<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateToken
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requireAdmin = 'false'): Response
    {
        $result = $this->authService->validateToken($request);

        if ($result['error'] !== null) {
            return response()->json('not authorized', 401);
        }

        $tokenData = $result['data'];

        // Check admin requirement if specified
        if ($requireAdmin === 'true' && !$this->authService->isAdmin($tokenData)) {
            return response()->json('not authorized', 401);
        }

        // Attach token data to request for use in controllers
        $request->attributes->set('token_data', $tokenData);

        return $next($request);
    }
}
