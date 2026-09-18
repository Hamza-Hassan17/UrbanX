<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverEarningsReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $driver,
        public array $row,
        public string $startDate,
        public string $endDate,
        public string $pdfContent
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Earnings Report ({$this->startDate} to {$this->endDate})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.driver-earnings-report',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, "earnings-{$this->startDate}-to-{$this->endDate}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
