<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyAttendanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly array   $attendanceData,
        public readonly string  $date,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Attendance Report – {$this->student->student_name} ({$this->date})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.daily-attendance',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
