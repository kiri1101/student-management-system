<?php

namespace App\Enums;

/**
 * A student's computed tuition-payment standing for access gating (#8). Never
 * persisted — always derived live from the fee schedule, validated payments,
 * and active deferrals by PaymentStandingService, since it depends on today.
 */
enum PaymentStanding: string
{
    case Cleared = 'cleared';
    case Blocked = 'blocked';
    case Deferred = 'deferred';

    public function label(): string
    {
        return match ($this) {
            self::Cleared => 'Cleared',
            self::Blocked => 'Blocked',
            self::Deferred => 'Deferred',
        };
    }
}
