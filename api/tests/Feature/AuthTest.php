<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user matching the existing schema
        $this->user = User::create([
            'username'        => 'testteacher',
            'password_hash'   => Hash::make('password123'),
            'email'           => 'test@college.edu',
            'full_name'       => 'Test Teacher',
            'role'            => 'faculty',
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);
    }

    // ── 1. Successful login ───────────────────────────────────────────────────
    public function test_successful_login_returns_tokens(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'access_token',
                         'refresh_token',
                         'token_type',
                         'expires_in',
                         'user' => ['user_id', 'username', 'email', 'role'],
                     ],
                 ])
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.token_type', 'bearer');
    }

    public function test_login_accepts_username_instead_of_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'testteacher', // username field accepts username too
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    // ── 2. Wrong password ─────────────────────────────────────────────────────
    public function test_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false])
                 ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_wrong_password_increments_failed_attempts(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'wrong',
        ]);

        $this->assertEquals(1, $this->user->fresh()->failed_attempts);
    }

    public function test_nonexistent_user_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@college.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    // ── 3. Account lockout after 5 failed attempts ────────────────────────────
    public function test_account_locks_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'test@college.edu',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be rejected with ACCOUNT_LOCKED
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123', // correct password, but account is locked
        ]);

        $response->assertStatus(423)
                 ->assertJsonPath('error.code', 'ACCOUNT_LOCKED');
    }

    public function test_locked_account_has_locked_until_set(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'test@college.edu',
                'password' => 'wrong',
            ]);
        }

        $this->assertNotNull($this->user->fresh()->locked_until);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        // Set some failed attempts
        $this->user->update(['failed_attempts' => 3]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $this->assertEquals(0, $this->user->fresh()->failed_attempts);
        $this->assertNull($this->user->fresh()->locked_until);
    }

    // ── 4. Token refresh ──────────────────────────────────────────────────────
    public function test_refresh_token_issues_new_access_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
                 ]);

        // New refresh token must be different (rotation)
        $this->assertNotEquals(
            $refreshToken,
            $response->json('data.refresh_token')
        );
    }

    public function test_invalid_refresh_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'completely-fake-token',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_used_refresh_token_cannot_be_reused(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        // Use it once
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken]);

        // Try to reuse — must fail
        $response = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken]);
        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'INVALID_REFRESH_TOKEN');
    }

    // ── 5. Logout ─────────────────────────────────────────────────────────────
    public function test_logout_returns_success(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('data.access_token');

        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    // ── 6. Denylist check — logged-out token rejected ─────────────────────────
    public function test_logged_out_token_is_rejected_by_denylist(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('data.access_token');

        // Logout — adds jti to Redis denylist
        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        // Attempt to use the same token on /me
        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'TOKEN_REVOKED');
    }

    // ── 7. /me endpoint ───────────────────────────────────────────────────────
    public function test_me_returns_authenticated_user(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@college.edu',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('data.access_token');

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.email', 'test@college.edu')
                 ->assertJsonPath('data.role', 'faculty');
    }

    public function test_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    // ── 8. Validation ─────────────────────────────────────────────────────────
    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_refresh_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);

        $response->assertStatus(422);
    }
}
