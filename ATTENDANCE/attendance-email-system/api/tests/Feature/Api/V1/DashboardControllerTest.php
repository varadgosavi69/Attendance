<?php

namespace Tests\Feature\Api\V1;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    private function makeStudent(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'roll_number'  => 'CSE' . random_int(10000, 99999),
            'student_name' => 'Test Student',
            'email'        => 'student_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $attributes));
    }

    // ── admin/teacher → faculty summary ───────────────────────────────────────
    public function test_admin_sees_faculty_summary_shape(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $this->makeStudent();

        $response = $this->getJson('/api/v1/reports/dashboard', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => ['total_students', 'avg_attendance', 'absentees_today', 'classes_today', 'recent_activity'],
                 ]);
    }

    // ── hod → department summary ──────────────────────────────────────────────
    public function test_hod_sees_department_summary_shape(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $this->makeStudent(['department' => 'CSE']);
        $this->makeStudent(['department' => 'ME']);

        $response = $this->getJson('/api/v1/reports/dashboard', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => ['department', 'total_students', 'avg_attendance', 'recent_summaries'],
                 ])
                 ->assertJsonPath('data.department', 'CSE')
                 ->assertJsonPath('data.total_students', 1);
    }

    // ── principal → college-wide summary ──────────────────────────────────────
    public function test_principal_sees_college_wide_summary_shape(): void
    {
        [, $headers] = $this->userWithRole('principal');
        $this->makeStudent(['department' => 'CSE']);
        $this->makeStudent(['department' => 'ME']);

        $response = $this->getJson('/api/v1/reports/dashboard', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_students', 'total_subjects', 'total_faculty', 'avg_attendance',
                         'department_counts', 'today_present', 'today_absent', 'today_total',
                     ],
                 ])
                 ->assertJsonPath('data.total_students', 2);
    }

    // ── auth failure ───────────────────────────────────────────────────────────
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/dashboard');

        $response->assertStatus(401);
    }
}
