<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class HodControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    // ── POST /hod/summary — happy path ────────────────────────────────────────
    public function test_hod_can_submit_attendance_summary(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/hod/summary', [
            'department'     => 'CSE',
            'semester'       => 4,
            'year'           => 2026,
            'date'           => now()->format('Y-m-d'),
            'total_students' => 60,
            'present_count'  => 54,
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertEquals(90.0, (float) $response->json('data.percentage'));

        $this->assertDatabaseHas('hod_attendance_summary', [
            'department'     => 'CSE',
            'semester'       => 4,
            'total_students' => 60,
            'present_count'  => 54,
        ]);
    }

    public function test_resubmitting_the_same_day_updates_existing_summary(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $date = now()->format('Y-m-d');

        $this->postJson('/api/v1/hod/summary', [
            'department' => 'CSE', 'semester' => 4, 'year' => 2026, 'date' => $date,
            'total_students' => 60, 'present_count' => 50,
        ], $headers);

        $this->postJson('/api/v1/hod/summary', [
            'department' => 'CSE', 'semester' => 4, 'year' => 2026, 'date' => $date,
            'total_students' => 60, 'present_count' => 58,
        ], $headers)->assertStatus(200);

        $this->assertDatabaseCount('hod_attendance_summary', 1);
        $this->assertDatabaseHas('hod_attendance_summary', ['present_count' => 58]);
    }

    // ── POST /hod/summary — auth failure (non-hod) ────────────────────────────
    public function test_non_hod_cannot_submit_attendance_summary(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/hod/summary', [
            'department' => 'CSE', 'semester' => 4, 'year' => 2026, 'date' => now()->format('Y-m-d'),
            'total_students' => 60, 'present_count' => 54,
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /hod/summary — validation errors ─────────────────────────────────
    public function test_submit_summary_validates_required_fields(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/hod/summary', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_submit_summary_rejects_present_count_greater_than_total(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/hod/summary', [
            'department' => 'CSE', 'semester' => 4, 'year' => 2026, 'date' => now()->format('Y-m-d'),
            'total_students' => 50, 'present_count' => 55,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ── GET /reports/hod/{department} — happy path ────────────────────────────
    public function test_hod_can_view_their_own_department_dashboard(): void
    {
        [$user, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $this->postJson('/api/v1/hod/summary', [
            'department' => 'CSE', 'semester' => 4, 'year' => 2026, 'date' => now()->format('Y-m-d'),
            'total_students' => 60, 'present_count' => 54,
        ], $headers);

        $response = $this->getJson('/api/v1/reports/hod/CSE', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['success', 'data', 'meta' => ['page', 'per_page', 'total']])
                 ->assertJsonPath('meta.total', 1);
    }

    // ── GET /reports/hod/{department} — auth failure (other dept) ─────────────
    public function test_hod_cannot_view_another_departments_dashboard(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->getJson('/api/v1/reports/hod/ME', $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── principal can view any department ─────────────────────────────────────
    public function test_principal_can_view_any_departments_dashboard(): void
    {
        [, $headers] = $this->userWithRole('principal');

        $response = $this->getJson('/api/v1/reports/hod/ME', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    // ── auth failure (no role at all) ──────────────────────────────────────────
    public function test_teacher_cannot_view_hod_department_dashboard(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->getJson('/api/v1/reports/hod/CSE', $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
