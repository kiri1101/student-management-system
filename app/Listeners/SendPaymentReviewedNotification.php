<?php

namespace App\Listeners;

use App\Events\PaymentReviewed;
use App\Mail\PaymentReviewedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPaymentReviewedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Mail the student the outcome of their payment review. PaymentReviewed
     * fires once per terminal decision (validate or reject), so the student
     * gets exactly one email per outcome, addressed to their account email.
     */
    public function handle(PaymentReviewed $event): void
    {
        $email = $event->submission->studentProfile?->user?->email;

        if ($email === null) {
            return;
        }

        Mail::to($email)->send(new PaymentReviewedMail($event->submission));
    }
}
