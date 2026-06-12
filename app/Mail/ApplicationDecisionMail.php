<?php

namespace App\Mail;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' — application decision: '.$this->decisionLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.application-decision',
            with: [
                'application' => $this->application,
                'decision' => $this->application->status,
                'decisionLabel' => $this->decisionLabel(),
                'matricule' => $this->matricule(),
            ],
        );
    }

    private function decisionLabel(): string
    {
        return match ($this->application->status) {
            ApplicationStatus::Admitted => 'Admitted',
            ApplicationStatus::Rejected => 'Not admitted',
            ApplicationStatus::Waitlisted => 'Waitlisted',
            // Withdrawn is only reachable through the SAO "restore prior
            // enrollment" merge (RestorePriorEnrollment), never a plain
            // SAO decision, so it reads as a reactivation to the applicant.
            ApplicationStatus::Withdrawn => 'Prior enrollment restored',
            default => ucfirst($this->application->status->value),
        };
    }

    /**
     * The matricule the applicant can now sign in with — present on the
     * Admitted path (freshly issued) and the merge path (historical one
     * restored), null for every other decision.
     */
    private function matricule(): ?string
    {
        if (! in_array($this->application->status, [ApplicationStatus::Admitted, ApplicationStatus::Withdrawn], strict: true)) {
            return null;
        }

        return $this->application->applicant?->studentProfile?->matricule;
    }
}
