<?php

namespace App\Events;

use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentSubmission $submission,
        public User $reviewedBy,
    ) {}
}
