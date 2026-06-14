<?php

namespace App\Events;

use App\Models\TuitionDeferral;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeferralReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TuitionDeferral $deferral,
        public User $reviewedBy,
    ) {}
}
