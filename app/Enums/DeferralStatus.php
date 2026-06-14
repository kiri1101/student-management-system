<?php

namespace App\Enums;

enum DeferralStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * A request is terminal once an accountant has approved or rejected it;
     * only `Requested` rows remain actionable in the review queue.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Requested;
    }
}
