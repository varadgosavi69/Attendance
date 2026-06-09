<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

#[OA\Tag(name: 'Auth', description: 'Authentication: login, token refresh, logout, current user')]
class AuthController extends Controller
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;
    private const REFRESH_TTL_MIN = 10080; // 7 days in minutes

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/login
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/auth/login',
        summary: 'Log in with email/username and password, receiving a JWT access + refresh token pair',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', description: 'Email or username', example: 'admin@college.edu'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'access_token', type: 'string'),
                    new OA\Property(property: 'refresh_token', type: 'string'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                    new OA\Property(property: 'expires_in', type: 'integer', description: 'Seconds until access token expiry'),
                    new OA\Property(property: 'user', type: 'object'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 423, description: 'Account locked due to repeated failed attempts', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // Accept username or email in the email field (matches legacy behaviour)
        $user = User::where('email', $request->email)
                    ->orWhere('username', $request->email)
                    ->first();

        if (! $user) {
            return $this->errorResponse('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
        }

        // ── Account lockout check ─────────────────────────────────────────────
        if ($user->locked_until && now()->lt($user->locked_until)) {
            $remaining = now()->diffInMinutes($user->locked_until, false);
            return $this->errorResponse(
                'ACCOUNT_LOCKED',
                "Account locked. Try again in {$remaining} minute(s).",
                423
            );
        }

        // ── Password verification (supports both password_hash and password) ──
        $passwordField = $user->password_hash ?? '';
        if (! Hash::check($request->password, $passwordField)) {
            $this->recordFailedAttempt($user);

            $attemptsLeft = self::MAX_ATTEMPTS - $user->fresh()->failed_attempts;
            $msg = $attemptsLeft > 0
                ? "Invalid credentials. {$attemptsLeft} attempt(s) remaining."
                : 'Account locked for ' . self::LOCKOUT_MINUTES . ' minutes.';

            return $this->errorResponse('INVALID_CREDENTIALS', $msg, 401);
        }

        // ── Successful login — reset lockout state ────────────────────────────
        $user->update([
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => now(),
        ]);

        // ── Issue JWT access token ────────────────────────────────────────────
        try {
            $accessToken = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return $this->errorResponse('TOKEN_ERROR', 'Could not create token.', 500);
        }

        // ── Issue refresh token → store in Redis ──────────────────────────────
        $refreshToken = $this->issueRefreshToken($user->user_id);

        return response()->json([
            'success' => true,
            'data'    => [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'bearer',
                'expires_in'    => config('jwt.ttl') * 60, // seconds
                'user'          => $this->formatUser($user),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/refresh
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/auth/refresh',
        summary: 'Exchange a valid refresh token for a new access + refresh token pair (rotates the refresh token)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [new OA\Property(property: 'refresh_token', type: 'string')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'New token pair issued', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'access_token', type: 'string'),
                    new OA\Property(property: 'refresh_token', type: 'string'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                    new OA\Property(property: 'expires_in', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Refresh token invalid, expired, or user not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        $incoming = $request->refresh_token;
        $redisKey = 'refresh:' . hash('sha256', $incoming);
        $userId   = Redis::get($redisKey);

        if (! $userId) {
            return $this->errorResponse('INVALID_REFRESH_TOKEN', 'Refresh token is invalid or expired.', 401);
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found.', 401);
        }

        // Rotate: delete old refresh token, issue new pair
        Redis::del($redisKey);
        $newAccessToken  = JWTAuth::fromUser($user);
        $newRefreshToken = $this->issueRefreshToken($user->user_id);

        return response()->json([
            'success' => true,
            'data'    => [
                'access_token'  => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type'    => 'bearer',
                'expires_in'    => config('jwt.ttl') * 60,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/logout
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Log out — denylists the current access token and (optionally) revokes the refresh token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [new OA\Property(property: 'refresh_token', type: 'string', nullable: true)],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'message', type: 'string')], type: 'object'),
            ])),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $jti     = $payload->get('jti');
            $exp     = $payload->get('exp');

            // Add jti to Redis denylist; TTL = remaining token lifetime
            $ttl = max(1, $exp - now()->timestamp);
            Redis::setex('jwt:denylist:' . $jti, $ttl, '1');

            // Delete refresh token if provided
            if ($request->has('refresh_token')) {
                $key = 'refresh:' . hash('sha256', $request->refresh_token);
                Redis::del($key);
            }

        } catch (JWTException $e) {
            // Token already invalid — still return success
        }

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Logged out successfully.'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/auth/me
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/auth/me',
        summary: 'Get the currently authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'full_name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'faculty_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'department', type: 'string', nullable: true),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Invalid or expired token', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function me(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return $this->errorResponse('TOKEN_INVALID', 'Invalid or expired token.', 401);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function issueRefreshToken(int $userId): string
    {
        $token    = Str::random(64);
        $redisKey = 'refresh:' . hash('sha256', $token);
        $ttlSecs  = self::REFRESH_TTL_MIN * 60;

        Redis::setex($redisKey, $ttlSecs, $userId);

        return $token;
    }

    private function recordFailedAttempt(User $user): void
    {
        $attempts = $user->failed_attempts + 1;

        $update = ['failed_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $update['locked_until']    = now()->addMinutes(self::LOCKOUT_MINUTES);
            $update['failed_attempts'] = 0; // reset counter so next lock cycle works
        }

        $user->update($update);
    }

    private function formatUser(User $user): array
    {
        return [
            'user_id'    => $user->user_id,
            'username'   => $user->username,
            'email'      => $user->email,
            'full_name'  => $user->full_name,
            'role'       => $user->role,
            'faculty_id' => $user->faculty_id,
            'department' => $user->department,
        ];
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
