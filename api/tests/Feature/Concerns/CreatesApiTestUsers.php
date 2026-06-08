<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

trait CreatesApiTestUsers
{
    /**
     * Create a User with the given role and return [$user, $authHeaders].
     *
     * @return array{0: User, 1: array<string, string>}
     */
    protected function userWithRole(string $role, array $attributes = []): array
    {
        $user = User::create(array_merge([
            'username'        => Str::lower($role) . '_' . Str::random(8),
            'password_hash'   => Hash::make('password123'),
            'email'           => Str::lower($role) . '_' . Str::random(8) . '@college.edu',
            'full_name'       => ucfirst($role) . ' Test User',
            'role'            => $role,
            'failed_attempts' => 0,
        ], $attributes));

        return [$user, $this->bearerHeaders($user)];
    }

    /**
     * @return array<string, string>
     */
    protected function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }
}
