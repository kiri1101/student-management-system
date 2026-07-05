<?php

namespace App\Events;

use App\Models\ResultDispute;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultDisputeReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ResultDispute $dispute,
    ) {}
}
