<?php

namespace App\Services;

use App\Jobs\SendAttendanceEmailJob;
use App\Jobs\SendDetentionEmailJob;
use App\Models\EmailLog;
use App\Models\Student;

class EmailService
{
    public function queueDailyAttendance(Student $student, array $attendanceData, string $date): EmailLog
    {
        if (! $student->parent_email) {
            throw new \RuntimeException("Student {$student->student_id} has no parent_email.");
        }

        $log = EmailLog::create([
            'student_id'      => $student->student_id,
            'recipient_email' => $student->parent_email,
            'email_type'      => 'daily_attendance',
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => now(),
        ]);

        SendAttendanceEmailJob::dispatch($log->log_id, $student->student_id, $attendanceData, $date)
            ->onQueue('emails');

        return $log;
    }

    public function queueDetentionNotice(Student $student, array $detentionData, string $month): EmailLog
    {
        if (! $student->parent_email) {
            throw new \RuntimeException("Student {$student->student_id} has no parent_email.");
        }

        $log = EmailLog::create([
            'student_id'      => $student->student_id,
            'recipient_email' => $student->parent_email,
            'email_type'      => 'detention_notice',
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => now(),
        ]);

        SendDetentionEmailJob::dispatch($log->log_id, $student->student_id, $detentionData, $month)
            ->onQueue('emails');

        return $log;
    }
}
