<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Parse and decode the token first (does NOT check blacklist yet)
            $payload = JWTAuth::parseToken()->getPayload();

            // Check our Redis denylist before anything else
            $jti = $payload->get('jti');
            if (app('redis')->exists('jwt:denylist:' . $jti)) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'TOKEN_REVOKED', 'message' => 'Token has been revoked.'],
                ], 401);
            }

            // Now authenticate (loads the User model)
            $user = JWTAuth::authenticate(JWTAuth::getToken());
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'USER_NOT_FOUND', 'message' => 'User not found.'],
                ], 401);
            }

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRED', 'message' => 'Token has expired.'],
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_INVALID', 'message' => 'Token is invalid.'],
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_MISSING', 'message' => 'Authorization token not provided.'],
            ], 401);
        }

        return $next($request);
    }
}
