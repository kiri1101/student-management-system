<?php

namespace App\Enums;

enum DegreeProgram: string
{
    case Hnd = 'hnd';
    case Bachelors = 'bachelors';
    case Masters = 'masters';

    /**
     * Human-readable label, mirroring the frontend `degreeLabel()` map
     * (`resources/js/lib/statusDisplay.ts`) so the two can't drift.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hnd => 'HND',
            self::Bachelors => 'Bachelors',
            self::Masters => 'Masters',
        };
    }
}
