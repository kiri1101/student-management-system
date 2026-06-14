<?php

namespace App\Mail;

use App\Enums\DeferralStatus;
use App\Models\TuitionDeferral;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeferralReviewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TuitionDeferral $deferral,
    ) {}

    public function envelope(): Envelope
    {
        $outcome = $this->deferral->status === DeferralStatus::Approved ? 'approved' : 'rejected';

        return new Envelope(
            subject: config('app.name').' — deferral request '.$outcome,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.deferral-reviewed',
            with: [
                'deferral' => $this->deferral,
                'status' => $this->deferral->status,
                'studentName' => $this->deferral->studentProfile?->user?->name,
            ],
        );
    }
}
