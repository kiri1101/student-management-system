<?php

namespace App\Concerns;

trait NormalizesPhoneNumbers
{
    /**
     * Canonicalize a phone number to E.164-ish form: a single leading `+`
     * followed by digits, with every separator (spaces, dashes, dots,
     * parentheses, etc.) stripped. So `+237 6 12-34.56(78)` becomes
     * `+237612345678`.
     *
     * The mandatory leading `+` is the namespace guarantee for the login
     * resolver: emails carry an `@`, matricules are `stm-…`, and employee IDs
     * must start with `[a-z0-9]` — none can begin with `+`, so a normalized
     * phone can never be confused with another identifier namespace.
     *
     * Returns null for blank input so callers can treat the column as optional.
     */
    protected static function normalizePhoneNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $hasLeadingPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            // No digits at all — hand the raw value back so the format rule
            // produces a validation error instead of a silent null.
            return $trimmed;
        }

        return ($hasLeadingPlus ? '+' : '').$digits;
    }
}
