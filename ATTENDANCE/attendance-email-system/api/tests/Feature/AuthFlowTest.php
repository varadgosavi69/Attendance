<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end auth flow: login → /me → logout → denylist.
 * Tests the full sequence as a single chain, ensuring each step depends
 * on the previous one. Individual auth edge cases live in AuthTest.php.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_login_me_logout_denylist_flow(): void
    {
        // 1. Setup
        User::create([
            'username'        => 'flowuser',
            'password_hash'   => Hash::make('secret99'),
            'email'           => 'flow@college.edu',
            'full_name'       => 'Flow Test User',
            'role'            => 'teacher',
            'failed_attempts' => 0,
        ]);

        // 2. Login — expect JWT
        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => 'flow@college.edu',
            'password' => 'secret99',
        ]);
        $login->assertStatus(200)->assertJsonPath('success', true);
        $token = $login->json('data.access_token');
        $this->assertNotEmpty($token);

        // 3. /me with valid token — expect user data back
        $me = $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"]);
        $me->assertStatus(200)
           ->assertJsonPath('data.email', 'flow@college.edu')
           ->assertJsonPath('data.role', 'teacher');

        // 4. Logout — expect success
        $logout = $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"]);
        $logout->assertStatus(200)->assertJsonPath('success', true);

        // 5. /me with same token — must be 401 (denylisted)
        $denied = $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"]);
        $denied->assertStatus(401)
               ->assertJsonPath('error.code', 'TOKEN_REVOKED');
    }
}
