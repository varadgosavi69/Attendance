<?php

namespace Tests\Feature\Api\V1;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class PrincipalControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    // ── GET /reports/principal — happy path ───────────────────────────────────
    public function test_principal_can_view_college_wide_overview(): void
    {
        [, $headers] = $this->userWithRole('principal');

        Student::create(['roll_number' => 'CSE00001', 'student_name' => 'A', 'email' => 'a@college.edu', 'department' => 'CSE', 'semester' => 3]);
        Student::create(['roll_number' => 'ME00001', 'student_name' => 'B', 'email' => 'b@college.edu', 'department' => 'ME', 'semester' => 5]);

        $response = $this->getJson('/api/v1/reports/principal', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_students', 'total_subjects', 'total_faculty', 'avg_attendance',
                         'department_counts', 'today_present', 'today_absent', 'today_total',
                     ],
                 ])
                 ->assertJsonPath('data.total_students', 2)
                 ->assertJsonCount(2, 'data.department_counts');
    }

    // ── auth failures ──────────────────────────────────────────────────────────
    public function test_non_principal_cannot_view_college_wide_overview(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->getJson('/api/v1/reports/principal', $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_overview_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/principal');

        $response->assertStatus(401);
    }
}
