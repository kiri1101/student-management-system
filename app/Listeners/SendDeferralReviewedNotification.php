<?php

namespace App\Listeners;

use App\Events\DeferralReviewed;
use App\Mail\DeferralReviewedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendDeferralReviewedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Mail the student the outcome of their deferral request. DeferralReviewed
     * fires once per terminal decision, so exactly one email per outcome.
     */
    public function handle(DeferralReviewed $event): void
    {
        $email = $event->deferral->studentProfile?->user?->email;

        if ($email === null) {
            return;
        }

        Mail::to($email)->send(new DeferralReviewedMail($event->deferral));
    }
}
