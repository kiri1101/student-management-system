<?php

namespace App\Enums;

enum CoursePlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * A course plan is terminal once SAO has approved it; an approved plan is
     * locked and no longer re-reviewable. Rejected plans return to the lecturer
     * for revision and resubmission, so they are not terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::Approved;
    }
}
