<?php

namespace Tests\Feature;

use App\Jobs\SendAttendanceEmailJob;
use App\Jobs\SendDetentionEmailJob;
use App\Models\EmailLog;
use App\Models\Student;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailQueueTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::create([
            'roll_number'   => 'CS001',
            'student_name'  => 'Test Student',
            'email'         => 'student@college.edu',
            'parent_email'  => 'parent@example.com',
            'department'    => 'CS',
            'semester'      => 3,
        ]);
    }

    public function test_queue_daily_attendance_creates_log_and_dispatches_job(): void
    {
        Queue::fake();

        $service = app(EmailService::class);
        $log = $service->queueDailyAttendance($this->student, [
            ['subject' => 'Math', 'status' => 'present', 'marked_at' => '09:00'],
        ], '2026-06-07');

        $this->assertDatabaseHas('email_logs', [
            'log_id'          => $log->log_id,
            'student_id'      => $this->student->student_id,
            'recipient_email' => 'parent@example.com',
            'email_type'      => 'daily_attendance',
            'status'          => 'queued',
        ]);

        Queue::assertPushedOn('emails', SendAttendanceEmailJob::class, function ($job) use ($log) {
            return $job->logId === $log->log_id
                && $job->studentId === $this->student->student_id
                && $job->date === '2026-06-07';
        });
    }

    public function test_queue_detention_notice_creates_log_and_dispatches_job(): void
    {
        Queue::fake();

        $service = app(EmailService::class);
        $log = $service->queueDetentionNotice($this->student, [
            'attendance_percentage' => 60.0,
            'required_percentage'   => 75,
        ], '2026-05');

        $this->assertDatabaseHas('email_logs', [
            'log_id'          => $log->log_id,
            'student_id'      => $this->student->student_id,
            'recipient_email' => 'parent@example.com',
            'email_type'      => 'detention_notice',
            'status'          => 'queued',
        ]);

        Queue::assertPushedOn('emails', SendDetentionEmailJob::class, function ($job) use ($log) {
            return $job->logId === $log->log_id
                && $job->month === '2026-05';
        });
    }

    public function test_queue_attendance_throws_when_no_parent_email(): void
    {
        Queue::fake();

        $student = Student::create([
            'roll_number'  => 'CS002',
            'student_name' => 'No Parent Email',
            'email'        => 'nope@college.edu',
            'parent_email' => null,
            'department'   => 'CS',
            'semester'     => 3,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no parent_email/');

        app(EmailService::class)->queueDailyAttendance($student, [], '2026-06-07');
    }

    public function test_queue_detention_throws_when_no_parent_email(): void
    {
        Queue::fake();

        $student = Student::create([
            'roll_number'  => 'CS003',
            'student_name' => 'No Parent 2',
            'email'        => 'nope2@college.edu',
            'parent_email' => null,
            'department'   => 'CS',
            'semester'     => 3,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no parent_email/');

        app(EmailService::class)->queueDetentionNotice($student, [], '2026-05');
    }

    public function test_send_attendance_job_marks_log_sent_on_success(): void
    {
        $log = EmailLog::create([
            'student_id'      => $this->student->student_id,
            'recipient_email' => 'parent@example.com',
            'email_type'      => 'daily_attendance',
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => now(),
        ]);

        \Illuminate\Support\Facades\Mail::fake();

        $job = new SendAttendanceEmailJob(
            $log->log_id,
            $this->student->student_id,
            [['subject' => 'Math', 'status' => 'present', 'marked_at' => '09:00']],
            '2026-06-07'
        );
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'log_id'   => $log->log_id,
            'status'   => 'sent',
            'attempts' => 1,
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\DailyAttendanceMail::class, function ($mail) {
            return $mail->hasTo('parent@example.com');
        });
    }

    public function test_send_attendance_job_marks_log_failed_on_error(): void
    {
        $log = EmailLog::create([
            'student_id'      => $this->student->student_id,
            'recipient_email' => 'parent@example.com',
            'email_type'      => 'daily_attendance',
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => now(),
        ]);

        $job = new SendAttendanceEmailJob($log->log_id, $this->student->student_id, [], '2026-06-07');
        $job->failed(new \Exception('SMTP connection refused'));

        $this->assertDatabaseHas('email_logs', [
            'log_id' => $log->log_id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseMissing('email_logs', [
            'log_id'        => $log->log_id,
            'error_message' => null,
        ]);
    }

    public function test_send_daily_attendance_command_outputs_warning_for_no_records(): void
    {
        $this->artisan('attendance:send-daily', ['--date' => '2000-01-01'])
            ->expectsOutputToContain('No attendance records found')
            ->assertExitCode(0);
    }
}
