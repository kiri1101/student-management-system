<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Validated => 'Validated',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * A submission is terminal once an accountant has validated or rejected it;
     * only `Submitted` rows remain actionable in the review queue.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Submitted;
    }
}
