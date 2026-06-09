<?php

namespace App\Jobs;

use App\Mail\DailyAttendanceMail;
use App\Models\EmailLog;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAttendanceEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int    $logId,
        public readonly int    $studentId,
        public readonly array  $attendanceData,
        public readonly string $date,
    ) {}

    public function handle(): void
    {
        $log     = EmailLog::findOrFail($this->logId);
        $student = Student::findOrFail($this->studentId);

        $log->increment('attempts');

        Mail::to($student->parent_email)
            ->send(new DailyAttendanceMail($student, $this->attendanceData, $this->date));

        $log->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        EmailLog::where('log_id', $this->logId)->update([
            'status'        => 'failed',
            'error_message' => substr($e->getMessage(), 0, 65535),
        ]);
    }
}
