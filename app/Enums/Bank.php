<?php

namespace App\Enums;

enum Bank: string
{
    case Uba = 'uba';
    case Afg = 'afg';
    case Africland = 'africland';
    case Scb = 'scb';
    case Sgc = 'sgc';

    /**
     * Canonical display name for each institute bank account a student can
     * deposit tuition into (CLAUDE.md project overview).
     */
    public function label(): string
    {
        return match ($this) {
            self::Uba => 'UBA',
            self::Afg => 'AFG',
            self::Africland => 'AFRICLAND',
            self::Scb => 'SCB',
            self::Sgc => 'SGC',
        };
    }
}
