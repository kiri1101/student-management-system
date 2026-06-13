<?php

namespace App\Mail;

use App\Enums\PaymentStatus;
use App\Models\PaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReviewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PaymentSubmission $submission,
    ) {}

    public function envelope(): Envelope
    {
        $outcome = $this->submission->status === PaymentStatus::Validated ? 'validated' : 'rejected';

        return new Envelope(
            subject: config('app.name').' — payment '.$outcome,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-reviewed',
            with: [
                'submission' => $this->submission,
                'status' => $this->submission->status,
                'studentName' => $this->submission->studentProfile?->user?->name,
            ],
        );
    }
}
