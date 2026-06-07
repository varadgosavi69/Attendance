<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DetentionNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly array   $detentionData,
        public readonly string  $month,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Detention Notice – {$this->student->student_name} ({$this->month})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.detention-notice',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
