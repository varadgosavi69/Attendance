<?php

namespace Tests\Unit;

use App\Models\Detention;
use App\Models\EmailLog;
use App\Models\Student;
use App\Services\DetentionService;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Inject a no-op EmailService so notifications are not dispatched
        $this->service = new DetentionService(Mockery::mock(EmailService::class));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeStudent(string $roll = 'CS001', ?string $parentEmail = null): Student
    {
        return Student::create([
            'roll_number'  => $roll,
            'student_name' => 'Test Student',
            'email'        => $roll . '@test.edu',
            'parent_email' => $parentEmail,
            'department'   => 'CS',
            'semester'     => 3,
        ]);
    }

    /**
     * Insert N present + M absent rows for a student in January 2026.
     * Cycles through days 1-28 and subject IDs 1-4 so that an arbitrarily
     * large number of records can be inserted without leaving January — no
     * unique constraint exists on (student, subject, date), so duplicates are fine.
     */
    private function insertAttendance(int $studentId, int $present, int $absent): void
    {
        $rows = [];
        $i    = 0;

        for ($p = 0; $p < $present; $p++, $i++) {
            $rows[] = [
                'student_id'      => $studentId,
                'subject_id'      => ($i % 4) + 1,
                'faculty_id'      => 1,
                'attendance_date' => sprintf('2026-01-%02d', ($i % 28) + 1),
                'status'          => 'Present',
                'marked_at'       => now(),
            ];
        }

        for ($a = 0; $a < $absent; $a++, $i++) {
            $rows[] = [
                'student_id'      => $studentId,
                'subject_id'      => ($i % 4) + 1,
                'faculty_id'      => 1,
                'attendance_date' => sprintf('2026-01-%02d', ($i % 28) + 1),
                'status'          => 'Absent',
                'marked_at'       => now(),
            ];
        }

        DB::table('attendance')->insert($rows);
    }

    // ── percentage calculation ────────────────────────────────────────────────

    public function test_student_with_no_classes_gets_zero_percent_and_is_detained(): void
    {
        $student = $this->makeStudent();

        $results = $this->service->calculateMonthly(2026, 1);

        $row = $results->firstWhere('student_id', $student->student_id);
        $this->assertNotNull($row);
        $this->assertEquals(0, $row['total_classes']);
        $this->assertEquals(0.0, $row['attendance_percentage']);
        $this->assertTrue($row['is_detained']);
    }

    public function test_student_with_100_percent_attendance_is_not_detained(): void
    {
        $student = $this->makeStudent('CS002');
        $this->insertAttendance($student->student_id, 20, 0);

        $results = $this->service->calculateMonthly(2026, 1);

        $row = $results->firstWhere('student_id', $student->student_id);
        $this->assertEquals(20, $row['total_classes']);
        $this->assertEquals(100.0, $row['attendance_percentage']);
        $this->assertFalse($row['is_detained']);
    }

    /** Boundary: exactly 75.0% must NOT be detained (threshold is strictly < 75). */
    public function test_student_at_exactly_75_percent_is_not_detained(): void
    {
        $student = $this->makeStudent('CS003');
        $this->insertAttendance($student->student_id, 75, 25); // 75/100 = 75.00%

        $results = $this->service->calculateMonthly(2026, 1);

        $row = $results->firstWhere('student_id', $student->student_id);
        $this->assertEquals(75.0, $row['attendance_percentage']);
        $this->assertFalse($row['is_detained']);
    }

    /** Boundary: 74% is one below the threshold — must be detained. */
    public function test_student_at_74_percent_is_detained(): void
    {
        $student = $this->makeStudent('CS004');
        $this->insertAttendance($student->student_id, 74, 26); // 74/100 = 74.00%

        $results = $this->service->calculateMonthly(2026, 1);

        $row = $results->firstWhere('student_id', $student->student_id);
        $this->assertEquals(74.0, $row['attendance_percentage']);
        $this->assertTrue($row['is_detained']);
    }

    // ── persistence ───────────────────────────────────────────────────────────

    public function test_detention_record_is_written_to_db(): void
    {
        $student = $this->makeStudent('CS005');
        $this->insertAttendance($student->student_id, 60, 40);

        $this->service->calculateMonthly(2026, 1);

        $this->assertDatabaseHas('detention', [
            'student_id'  => $student->student_id,
            'is_detained' => true,
        ]);
    }

    public function test_running_twice_upserts_not_duplicates(): void
    {
        $student = $this->makeStudent('CS006');
        $this->insertAttendance($student->student_id, 60, 40);

        $this->service->calculateMonthly(2026, 1);
        $this->service->calculateMonthly(2026, 1);

        $this->assertEquals(
            1,
            Detention::where('student_id', $student->student_id)->count()
        );
    }

    public function test_returns_all_students_including_those_with_no_records(): void
    {
        $s1 = $this->makeStudent('CS007');
        $s2 = $this->makeStudent('CS008');
        $this->insertAttendance($s1->student_id, 80, 20);
        // s2 gets no attendance rows → appears with total_classes=0

        $results = $this->service->calculateMonthly(2026, 1);

        $this->assertCount(2, $results);
        $this->assertEquals(0, $results->firstWhere('student_id', $s2->student_id)['total_classes']);
    }

    // ── queueNotifications ────────────────────────────────────────────────────

    public function test_queue_notifications_skips_detained_student_with_no_parent_email(): void
    {
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldNotReceive('queueDetentionNotice');
        $service = new DetentionService($emailService);

        $student = $this->makeStudent('CS009', null); // no parent_email

        $students = collect([[
            'student_id'            => $student->student_id,
            'is_detained'           => true,
            'attendance_percentage' => 60.0,
            'attended_classes'      => 6,
            'total_classes'         => 10,
        ]]);

        $result = $service->queueNotifications($students, 2026, 1);

        $this->assertEquals(0, $result['queued']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_queue_notifications_dispatches_for_student_with_parent_email(): void
    {
        $log          = new EmailLog();
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldReceive('queueDetentionNotice')->once()->andReturn($log);
        $service = new DetentionService($emailService);

        $student = $this->makeStudent('CS010', 'parent@test.edu');

        $students = collect([[
            'student_id'            => $student->student_id,
            'is_detained'           => true,
            'attendance_percentage' => 60.0,
            'attended_classes'      => 6,
            'total_classes'         => 10,
        ]]);

        $result = $service->queueNotifications($students, 2026, 1);

        $this->assertEquals(1, $result['queued']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_queue_notifications_only_processes_detained_students(): void
    {
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldNotReceive('queueDetentionNotice');
        $service = new DetentionService($emailService);

        $student = $this->makeStudent('CS011', 'parent@test.edu');

        $students = collect([[
            'student_id'            => $student->student_id,
            'is_detained'           => false, // not detained
            'attendance_percentage' => 80.0,
            'attended_classes'      => 8,
            'total_classes'         => 10,
        ]]);

        $result = $service->queueNotifications($students, 2026, 1);

        $this->assertEquals(0, $result['queued']);
        $this->assertEquals(0, $result['skipped']);
    }
}
