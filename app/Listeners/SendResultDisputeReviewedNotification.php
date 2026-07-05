<?php

namespace App\Listeners;

use App\Events\ResultDisputeReviewed;
use App\Notifications\ResultDisputeReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendResultDisputeReviewedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notify the disputing student their dispute reached a terminal outcome.
     * Fires once per terminal review; the interim UnderReview move emits no event.
     */
    public function handle(ResultDisputeReviewed $event): void
    {
        $user = $event->dispute->studentProfile?->user;

        if ($user === null) {
            return;
        }

        $user->notify(new ResultDisputeReviewedNotification($event->dispute));
    }
}
