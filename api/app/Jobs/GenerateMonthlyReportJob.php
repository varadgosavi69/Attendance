<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\Student;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Queueable;

    public int   $tries  = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $month, // e.g. "2026-05"
    ) {}

    public function handle(EmailService $emailService): void
    {
        // Fetch all detained students for the month and dispatch individual notices
        $detentions = \App\Models\Detention::with('student')
            ->where('month', 'like', $this->month . '%')
            ->where('is_detained', true)
            ->get();

        foreach ($detentions as $detention) {
            $student = $detention->student;

            if (! $student || ! $student->parent_email) {
                continue;
            }

            $emailService->queueDetentionNotice($student, [
                'attendance_percentage' => $detention->attendance_percentage ?? null,
                'required_percentage'   => 75,
            ], $this->month);
        }
    }
}
