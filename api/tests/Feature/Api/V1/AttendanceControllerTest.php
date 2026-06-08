<?php

namespace Tests\Feature\Api\V1;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    private function makeFacultyWithSubject(): array
    {
        $faculty = Faculty::create([
            'faculty_name' => 'Dr. Test Faculty',
            'email'        => 'faculty_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
        ]);

        $subject = Subject::create([
            'subject_name' => 'Data Structures',
            'subject_code' => 'CS' . random_int(100, 999),
            'department'   => 'CSE',
            'semester'     => 3,
        ]);

        DB::table('faculty_subjects')->insert([
            'faculty_id' => $faculty->faculty_id,
            'subject_id' => $subject->subject_id,
        ]);

        [$user, $headers] = $this->userWithRole('teacher', ['faculty_id' => $faculty->faculty_id]);

        return [$faculty, $subject, $user, $headers];
    }

    private function makeStudent(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'roll_number'  => 'CSE' . random_int(1000, 9999),
            'student_name' => 'Test Student',
            'email'        => 'student_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $attributes));
    }

    // ── POST /attendance — happy path ─────────────────────────────────────────
    public function test_teacher_can_mark_attendance_for_owned_subject(): void
    {
        [, $subject, , $headers] = $this->makeFacultyWithSubject();
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.count', 1);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->student_id,
            'subject_id' => $subject->subject_id,
            'status'     => 'Present',
        ]);
    }

    // ── POST /attendance — auth failure (not the subject's faculty) ───────────
    public function test_teacher_cannot_mark_attendance_for_unowned_subject(): void
    {
        [, , , $headers] = $this->makeFacultyWithSubject();
        $student = $this->makeStudent();

        $otherSubject = Subject::create([
            'subject_name' => 'Operating Systems',
            'subject_code' => 'CS' . random_int(100, 999),
            'department'   => 'CSE',
            'semester'     => 5,
        ]);

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $otherSubject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(403)
                 ->assertJson(['success' => false])
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /attendance — role check ─────────────────────────────────────────
    public function test_student_role_cannot_mark_attendance(): void
    {
        [, $subject, , ] = $this->makeFacultyWithSubject();
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => ['1' => 'Present'],
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /attendance — validation errors ──────────────────────────────────
    public function test_mark_attendance_validates_status_values(): void
    {
        [, $subject, , $headers] = $this->makeFacultyWithSubject();
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'NotAStatus'],
        ], $headers);

        $response->assertStatus(422)
                 ->assertJson(['success' => false])
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_mark_attendance_requires_subject_id_date_and_records(): void
    {
        [, , , $headers] = $this->makeFacultyWithSubject();

        $response = $this->postJson('/api/v1/attendance', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_mark_attendance_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => 1,
            'date'       => now()->format('Y-m-d'),
            'records'    => ['1' => 'Present'],
        ]);

        $response->assertStatus(401);
    }

    // ── GET /attendance/students — happy + validation ─────────────────────────
    public function test_teacher_can_list_students_for_marking(): void
    {
        [, , , $headers] = $this->makeFacultyWithSubject();
        $this->makeStudent(['roll_number' => 'CSE0001', 'semester' => 3, 'department' => 'CSE']);
        $this->makeStudent(['roll_number' => 'CSE0002', 'semester' => 3, 'department' => 'CSE']);

        $response = $this->getJson('/api/v1/attendance/students?semester=3&branch=CSE', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');
    }

    public function test_list_students_requires_semester_and_branch(): void
    {
        [, , , $headers] = $this->makeFacultyWithSubject();

        $response = $this->getJson('/api/v1/attendance/students', $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ── GET /attendance/subjects — happy path ─────────────────────────────────
    public function test_teacher_can_list_their_subjects(): void
    {
        [, $subject, , $headers] = $this->makeFacultyWithSubject();

        $response = $this->getJson('/api/v1/attendance/subjects', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonFragment(['subject_id' => $subject->subject_id]);
    }

    // ── GET /attendance/monthly/{student} — happy + 404 ────────────────────────
    public function test_monthly_summary_returns_per_subject_breakdown(): void
    {
        [$faculty, $subject, , $headers] = $this->makeFacultyWithSubject();
        $student = $this->makeStudent();

        $month = now()->subMonthNoOverflow();

        DB::table('attendance')->insert([
            ['student_id' => $student->student_id, 'subject_id' => $subject->subject_id, 'faculty_id' => $faculty->faculty_id, 'attendance_date' => $month->copy()->day(2)->format('Y-m-d'), 'status' => 'Present'],
            ['student_id' => $student->student_id, 'subject_id' => $subject->subject_id, 'faculty_id' => $faculty->faculty_id, 'attendance_date' => $month->copy()->day(3)->format('Y-m-d'), 'status' => 'Absent'],
        ]);

        $response = $this->getJson("/api/v1/attendance/monthly/{$student->student_id}?month={$month->format('Y-m')}", $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.total_classes', 2)
                 ->assertJsonPath('data.attended_classes', 1);

        $this->assertEquals(50.0, (float) $response->json('data.attendance_percentage'));
    }

    public function test_monthly_summary_404_for_nonexistent_student(): void
    {
        [, , , $headers] = $this->makeFacultyWithSubject();

        $response = $this->getJson('/api/v1/attendance/monthly/999999', $headers);

        $response->assertStatus(404);
    }
}
