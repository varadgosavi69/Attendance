<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendDetentionEmailJob;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class DetentionControllerTest extends TestCase
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

    private function seedAttendance(Student $student, string $monthStart, int $present, int $absent): void
    {
        $rows = [];
        $day  = 1;

        for ($i = 0; $i < $present; $i++, $day++) {
            $rows[] = [
                'student_id'      => $student->student_id,
                'subject_id'      => 1,
                'faculty_id'      => 1,
                'attendance_date' => date('Y-m-d', strtotime("$monthStart +" . ($day - 1) . ' days')),
                'status'          => 'Present',
            ];
        }

        for ($i = 0; $i < $absent; $i++, $day++) {
            $rows[] = [
                'student_id'      => $student->student_id,
                'subject_id'      => 1,
                'faculty_id'      => 1,
                'attendance_date' => date('Y-m-d', strtotime("$monthStart +" . ($day - 1) . ' days')),
                'status'          => 'Absent',
            ];
        }

        DB::table('attendance')->insert($rows);
    }

    // ── GET /reports/detention — happy path ───────────────────────────────────
    public function test_hod_can_list_detained_students(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $month = now()->subMonthNoOverflow()->startOfMonth();
        $student = $this->makeStudent(['parent_email' => 'parent@example.com']);

        DB::table('detention')->insert([
            'student_id'            => $student->student_id,
            'month'                 => $month->format('Y-m-d') . ' 00:00:00',
            'total_classes'         => 20,
            'attended_classes'      => 10,
            'attendance_percentage' => 50.00,
            'is_detained'           => true,
        ]);

        $response = $this->getJson('/api/v1/reports/detention?month=' . $month->format('Y-m'), $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['success', 'data', 'meta' => ['page', 'per_page', 'total', 'month', 'threshold']])
                 ->assertJsonPath('meta.total', 1)
                 ->assertJsonPath('data.0.student_id', $student->student_id);
    }

    public function test_listing_detention_requires_hod_or_principal_role(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->getJson('/api/v1/reports/detention', $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_listing_detention_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/detention');

        $response->assertStatus(401);
    }

    // ── POST /reports/detention/generate — happy path (no emails) ─────────────
    public function test_principal_can_generate_detention_report_without_emails(): void
    {
        Queue::fake();
        [, $headers] = $this->userWithRole('principal');

        $month = now()->subMonthNoOverflow();
        $monthStart = $month->copy()->startOfMonth()->format('Y-m-d');

        $detainedStudent = $this->makeStudent(['parent_email' => 'parent@example.com']);
        $this->seedAttendance($detainedStudent, $monthStart, present: 5, absent: 15); // 25%

        $okStudent = $this->makeStudent(['parent_email' => 'parent2@example.com']);
        $this->seedAttendance($okStudent, $monthStart, present: 18, absent: 2); // 90%

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'year'        => (int) $month->format('Y'),
            'month'       => (int) $month->format('m'),
            'send_emails' => false,
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.detained_count', 1)
                 ->assertJsonPath('data.emails_queued', 0)
                 ->assertJsonPath('data.emails_skipped', 0);

        $this->assertDatabaseHas('detention', ['student_id' => $detainedStudent->student_id, 'is_detained' => true]);
        $this->assertDatabaseHas('detention', ['student_id' => $okStudent->student_id, 'is_detained' => false]);

        Queue::assertNothingPushed();
    }

    public function test_regenerating_the_same_month_updates_existing_rows_instead_of_duplicating(): void
    {
        Queue::fake();
        [, $headers] = $this->userWithRole('principal');

        $month = now()->subMonthNoOverflow();
        $monthStart = $month->copy()->startOfMonth()->format('Y-m-d');

        $student = $this->makeStudent(['parent_email' => 'parent@example.com']);
        $this->seedAttendance($student, $monthStart, present: 5, absent: 15); // 25%

        $payload = [
            'year'        => (int) $month->format('Y'),
            'month'       => (int) $month->format('m'),
            'send_emails' => false,
        ];

        $this->postJson('/api/v1/reports/detention/generate', $payload, $headers)->assertStatus(200);
        $this->postJson('/api/v1/reports/detention/generate', $payload, $headers)->assertStatus(200);

        $this->assertDatabaseCount('detention', 1);
        $this->assertDatabaseHas('detention', ['student_id' => $student->student_id, 'attended_classes' => 5]);
    }

    // ── POST /reports/detention/generate — with notification emails ───────────
    public function test_principal_can_generate_detention_report_and_queue_emails(): void
    {
        Queue::fake();
        [, $headers] = $this->userWithRole('principal');

        $month = now()->subMonthNoOverflow();
        $monthStart = $month->copy()->startOfMonth()->format('Y-m-d');

        $detainedStudent = $this->makeStudent(['parent_email' => 'parent@example.com']);
        $this->seedAttendance($detainedStudent, $monthStart, present: 5, absent: 15); // 25%

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'year'        => (int) $month->format('Y'),
            'month'       => (int) $month->format('m'),
            'send_emails' => true,
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.detained_count', 1)
                 ->assertJsonPath('data.emails_queued', 1)
                 ->assertJsonPath('data.emails_skipped', 0);

        Queue::assertPushedOn('emails', SendDetentionEmailJob::class, function ($job) use ($detainedStudent) {
            return $job->studentId === $detainedStudent->student_id;
        });
    }

    // ── auth failures ──────────────────────────────────────────────────────────
    public function test_non_principal_cannot_generate_detention_report(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);

        $response = $this->postJson('/api/v1/reports/detention/generate', [], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── validation errors ──────────────────────────────────────────────────────
    public function test_generate_validates_month_range(): void
    {
        [, $headers] = $this->userWithRole('principal');

        $response = $this->postJson('/api/v1/reports/detention/generate', [
            'month' => 13,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
