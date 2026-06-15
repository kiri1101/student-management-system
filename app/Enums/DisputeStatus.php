<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under review',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Resolved and Rejected are terminal: a dispute in either state can no longer
     * be re-decided.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Resolved, self::Rejected => true,
            self::Open, self::UnderReview => false,
        };
    }
}
