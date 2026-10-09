<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReportReady extends Mailable
{
    public function __construct(public string $label, public string $recipient, public string $pdf, public string $filename) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Moderation complete: {$this->label}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice', with: [
            'heading' => "Moderation complete: {$this->label}",
            'body' => "Dear {$this->recipient}, moderation of {$this->label} is complete. The signed, read-only report is attached.",
            'url' => null,
            'cta' => null,
        ]);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
