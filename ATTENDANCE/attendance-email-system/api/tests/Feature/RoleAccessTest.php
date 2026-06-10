<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

/**
 * Verifies that the RoleCheck middleware grants/denies access correctly.
 *
 * Convention: for each endpoint we test one role that MUST have access (200/2xx)
 * and one role that MUST NOT (403). Unauthenticated (401) is covered separately.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeSubjectWithFaculty(): array
    {
        $faculty = Faculty::create([
            'faculty_name' => 'Test Faculty',
            'email'        => 'faculty_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
        ]);
        $subject = Subject::create([
            'subject_name' => 'Test Subject',
            'subject_code' => 'TST' . random_int(100, 999),
            'department'   => 'CSE',
            'semester'     => 3,
        ]);
        DB::table('faculty_subjects')->insert([
            'faculty_id' => $faculty->faculty_id,
            'subject_id' => $subject->subject_id,
        ]);
        return [$faculty, $subject];
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'roll_number'  => 'CSE' . random_int(10000, 99999),
            'student_name' => 'Role Student',
            'email'        => 'rs_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ]);
    }

    // ── POST /attendance — teacher:200, hod:403 ───────────────────────────────

    public function test_teacher_can_mark_attendance(): void
    {
        [$faculty, $subject] = $this->makeSubjectWithFaculty();
        [, $headers] = $this->userWithRole('teacher', ['faculty_id' => $faculty->faculty_id]);
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(200);
    }

    public function test_hod_cannot_mark_attendance(): void
    {
        [$faculty, $subject] = $this->makeSubjectWithFaculty();
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(403);
    }

    public function test_principal_cannot_mark_attendance(): void
    {
        [, $subject] = $this->makeSubjectWithFaculty();
        [, $headers] = $this->userWithRole('principal');
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(403);
    }

    // ── GET /reports/detention — hod:200, teacher:403 ─────────────────────────

    public function test_hod_can_view_detention_report(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->getJson('/api/v1/reports/detention', $headers);

        $response->assertStatus(200);
    }

    public function test_principal_can_view_detention_report(): void
    {
        [, $headers] = $this->userWithRole('principal');

        $response = $this->getJson('/api/v1/reports/detention', $headers);

        $response->assertStatus(200);
    }

    public function test_teacher_cannot_view_detention_report(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->getJson('/api/v1/reports/detention', $headers);

        $response->assertStatus(403);
    }

    // ── POST /reports/detention/generate — principal:200, hod:403 ────────────

    public function test_principal_can_generate_detention_report(): void
    {
        [, $headers] = $this->userWithRole('principal');

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'year'  => 2026,
            'month' => 1,
        ], $headers);

        // 200 (success) or 422 (validation) are both acceptable — we're testing access, not logic
        $response->assertStatus(200);
    }

    public function test_hod_cannot_generate_detention_report(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'year'  => 2026,
            'month' => 1,
        ], $headers);

        $response->assertStatus(403);
    }

    public function test_teacher_cannot_generate_detention_report(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'year'  => 2026,
            'month' => 1,
        ], $headers);

        $response->assertStatus(403);
    }

    // ── POST /students (admin only) — admin:200, teacher:403 ─────────────────

    public function test_admin_can_create_student(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'ROLE001',
            'student_name' => 'Role Test',
            'email'        => 'roletest@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $headers);

        $response->assertStatus(201);
    }

    public function test_teacher_cannot_create_student(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'ROLE002',
            'student_name' => 'Role Test 2',
            'email'        => 'roletest2@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $headers);

        $response->assertStatus(403);
    }

    public function test_hod_cannot_create_student(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'ROLE003',
            'student_name' => 'Role Test 3',
            'email'        => 'roletest3@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $headers);

        $response->assertStatus(403);
    }

    // ── GET /students — any authenticated role:200, unauthenticated:401 ───────

    public function test_teacher_can_list_students(): void
    {
        [, $headers] = $this->userWithRole('teacher');
        $this->getJson('/api/v1/students', $headers)->assertStatus(200);
    }

    public function test_hod_can_list_students(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $this->getJson('/api/v1/students', $headers)->assertStatus(200);
    }

    public function test_principal_can_list_students(): void
    {
        [, $headers] = $this->userWithRole('principal');
        $this->getJson('/api/v1/students', $headers)->assertStatus(200);
    }

    public function test_unauthenticated_cannot_list_students(): void
    {
        $this->getJson('/api/v1/students')->assertStatus(401);
    }

    // ── POST /hod/summary — hod:200, teacher:403 ─────────────────────────────

    public function test_hod_can_submit_summary(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/hod/summary', [
            'department'     => 'CSE',
            'semester'       => 3,
            'year'           => 2026,
            'date'           => now()->format('Y-m-d'),
            'total_students' => 50,
            'present_count'  => 45,
        ], $headers);

        $response->assertStatus(200);
    }

    public function test_teacher_cannot_submit_hod_summary(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/hod/summary', [
            'department'     => 'CSE',
            'semester'       => 3,
            'year'           => 2026,
            'date'           => now()->format('Y-m-d'),
            'total_students' => 50,
            'present_count'  => 45,
        ], $headers);

        $response->assertStatus(403);
    }

    // ── GET /reports/principal — principal:200, teacher:403 ──────────────────

    public function test_principal_can_view_principal_report(): void
    {
        [, $headers] = $this->userWithRole('principal');
        $this->getJson('/api/v1/reports/principal', $headers)->assertStatus(200);
    }

    public function test_teacher_cannot_view_principal_report(): void
    {
        [, $headers] = $this->userWithRole('teacher');
        $this->getJson('/api/v1/reports/principal', $headers)->assertStatus(403);
    }

    public function test_hod_cannot_view_principal_report(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $this->getJson('/api/v1/reports/principal', $headers)->assertStatus(403);
    }
}
