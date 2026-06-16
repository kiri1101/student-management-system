<?php

namespace App\Enums;

enum SessionChangeType: string
{
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
        };
    }
}
