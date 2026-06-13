<?php

namespace App\Listeners;

use App\Events\ApplicationDecided;
use App\Mail\ApplicationDecisionMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendApplicationDecisionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Mail the applicant their decision (AUDIT.md AUD-002). ApplicationDecided
     * fires once per terminal decision — SAO decide or the restore-prior-
     * enrollment merge — so the applicant gets exactly one email per outcome,
     * addressed to the contact email captured on the application form.
     */
    public function handle(ApplicationDecided $event): void
    {
        Mail::to($event->application->contact_email)
            ->send(new ApplicationDecisionMail($event->application));
    }
}
