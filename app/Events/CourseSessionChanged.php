<?php

namespace App\Events;

use App\Enums\SessionChangeType;
use App\Models\CourseSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseSessionChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CourseSession $session,
        public SessionChangeType $type,
        public ?string $reason = null,
        public ?CarbonImmutable $previousScheduledFor = null,
    ) {}
}
