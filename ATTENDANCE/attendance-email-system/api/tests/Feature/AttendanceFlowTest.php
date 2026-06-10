<?php

namespace Tests\Feature;

use App\Jobs\SendAttendanceEmailJob;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function scaffoldFacultySubject(): array
    {
        $faculty = Faculty::create([
            'faculty_name' => 'Dr. Flow Faculty',
            'email'        => 'flow_faculty_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
        ]);

        $subject = Subject::create([
            'subject_name' => 'Algorithms',
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

    private function makeStudent(array $extra = []): Student
    {
        return Student::create(array_merge([
            'roll_number'  => 'CSE' . random_int(10000, 99999),
            'student_name' => 'Flow Student',
            'email'        => 'flow_' . uniqid() . '@college.edu',
            'parent_email' => 'parent_' . uniqid() . '@example.com',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $extra));
    }

    // ── mark attendance ───────────────────────────────────────────────────────

    public function test_marking_attendance_persists_present_record(): void
    {
        [, $subject, , $headers] = $this->scaffoldFacultySubject();
        $student = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [$student->student_id => 'Present'],
        ], $headers);

        $response->assertStatus(200)->assertJsonPath('data.count', 1);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->student_id,
            'subject_id' => $subject->subject_id,
            'status'     => 'Present',
        ]);
    }

    public function test_marking_attendance_twice_for_same_slot_upserts_not_duplicates(): void
    {
        [, $subject, , $headers] = $this->scaffoldFacultySubject();
        $student = $this->makeStudent();
        $date    = now()->format('Y-m-d');

        $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => $date,
            'records'    => [$student->student_id => 'Present'],
        ], $headers)->assertStatus(200);

        $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => $date,
            'records'    => [$student->student_id => 'Absent'],
        ], $headers)->assertStatus(200);

        // Upsert: the second call updates the existing row — only one row in the slot
        $this->assertEquals(
            1,
            DB::table('attendance')
              ->where('student_id', $student->student_id)
              ->where('subject_id', $subject->subject_id)
              ->whereDate('attendance_date', $date)
              ->count()
        );

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->student_id,
            'status'     => 'Absent',
        ]);
    }

    public function test_marking_attendance_for_a_batch_persists_all_records(): void
    {
        [, $subject, , $headers] = $this->scaffoldFacultySubject();
        $s1 = $this->makeStudent();
        $s2 = $this->makeStudent();
        $s3 = $this->makeStudent();

        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => $subject->subject_id,
            'date'       => now()->format('Y-m-d'),
            'records'    => [
                $s1->student_id => 'Present',
                $s2->student_id => 'Absent',
                $s3->student_id => 'Leave',
            ],
        ], $headers);

        $response->assertStatus(200)->assertJsonPath('data.count', 3);
        $this->assertDatabaseHas('attendance', ['student_id' => $s1->student_id, 'status' => 'Present']);
        $this->assertDatabaseHas('attendance', ['student_id' => $s2->student_id, 'status' => 'Absent']);
        $this->assertDatabaseHas('attendance', ['student_id' => $s3->student_id, 'status' => 'Leave']);
    }

    // ── email queue triggered by scheduler command ────────────────────────────

    public function test_send_daily_command_queues_job_for_students_with_attendance_today(): void
    {
        Queue::fake();

        $student = $this->makeStudent(['parent_email' => 'p@example.com']);
        $today   = now()->format('Y-m-d');

        DB::table('attendance')->insert([
            'student_id'      => $student->student_id,
            'subject_id'      => 1,
            'faculty_id'      => 1,
            'attendance_date' => $today,
            'status'          => 'Present',
            'marked_at'       => now(),
        ]);

        $this->artisan('attendance:send-daily', ['--date' => $today])->assertExitCode(0);

        Queue::assertPushedOn('emails', SendAttendanceEmailJob::class);
    }

    public function test_send_daily_command_does_not_queue_for_students_without_parent_email(): void
    {
        Queue::fake();

        $student = $this->makeStudent(['parent_email' => null]);
        $today   = now()->format('Y-m-d');

        DB::table('attendance')->insert([
            'student_id'      => $student->student_id,
            'subject_id'      => 1,
            'faculty_id'      => 1,
            'attendance_date' => $today,
            'status'          => 'Present',
            'marked_at'       => now(),
        ]);

        $this->artisan('attendance:send-daily', ['--date' => $today])->assertExitCode(0);

        Queue::assertNotPushed(SendAttendanceEmailJob::class);
    }

    public function test_unauthenticated_request_cannot_mark_attendance(): void
    {
        $response = $this->postJson('/api/v1/attendance', [
            'subject_id' => 1,
            'date'       => '2026-06-08',
            'records'    => ['1' => 'Present'],
        ]);

        $response->assertStatus(401);
    }
}
