<?php

namespace App\Listeners;

use App\Events\ApplicationDocumentsRequested;
use App\Mail\ApplicationDocumentsRequestedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendDocumentsRequestedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Mail the applicant the list of rejected documents (#80). The event fires
     * once per entry into DocumentsRequested — first triage or a re-entry after
     * a failed resubmission cycle — so each request round emails exactly once,
     * addressed to the contact email captured on the application form.
     */
    public function handle(ApplicationDocumentsRequested $event): void
    {
        Mail::to($event->application->contact_email)
            ->send(new ApplicationDocumentsRequestedMail($event->application));
    }
}
