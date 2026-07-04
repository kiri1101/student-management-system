<?php

namespace App\Mail;

use App\Enums\ApplicationDocumentStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationDocumentsRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' — documents requested for your application',
        );
    }

    public function content(): Content
    {
        $rejectedDocuments = $this->application->documents()
            ->where('status', ApplicationDocumentStatus::Rejected->value)
            ->with('documentType:id,name,code')
            ->get();

        return new Content(
            markdown: 'mail.application-documents-requested',
            with: [
                'application' => $this->application,
                'rejectedDocuments' => $rejectedDocuments,
                'applicationUrl' => route('application.show', $this->application),
            ],
        );
    }
}
