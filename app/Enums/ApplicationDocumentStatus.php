<?php

namespace App\Enums;

enum ApplicationDocumentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }
}
