<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Student;
use App\Services\EmailService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:send-daily {--date= : Date in Y-m-d format (default: today)}')]
#[Description('Queue daily attendance notification emails to parents of all students')]
class SendDailyAttendance extends Command
{
    public function handle(EmailService $emailService): int
    {
        $date = $this->option('date') ?? now()->toDateString();

        $this->info("Queuing attendance emails for {$date}…");

        // Load all students who have attendance records for the date and have a parent_email
        $students = Student::whereNotNull('parent_email')
            ->whereHas('attendance', fn ($q) => $q->whereDate('attendance_date', $date))
            ->with(['attendance' => fn ($q) => $q->whereDate('attendance_date', $date)
                ->with('subject')])
            ->get();

        if ($students->isEmpty()) {
            $this->warn("No attendance records found for {$date}.");
            return self::SUCCESS;
        }

        $queued = 0;
        foreach ($students as $student) {
            $attendanceData = $student->attendance->map(fn ($a) => [
                'subject'   => $a->subject->subject_name ?? 'N/A',
                'status'    => $a->status,
                'marked_at' => $a->marked_at?->format('H:i'),
            ])->toArray();

            try {
                $emailService->queueDailyAttendance($student, $attendanceData, $date);
                $queued++;
            } catch (\Throwable $e) {
                $this->error("Skipped student {$student->student_id}: {$e->getMessage()}");
            }
        }

        $this->info("Queued {$queued} email(s) successfully.");

        return self::SUCCESS;
    }
}
