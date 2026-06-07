<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleCheck
{
    /**
     * Usage in routes: middleware('role:admin,teacher')
     * Passes if the authenticated user's role is in the allowed list.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        try {
            $payload  = JWTAuth::parseToken()->getPayload();
            $userRole = $payload->get('role');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_INVALID', 'message' => 'Invalid or missing token.'],
            ], 401);
        }

        if (! in_array($userRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
