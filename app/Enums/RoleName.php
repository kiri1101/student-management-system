<?php

namespace App\Enums;

enum RoleName: string
{
    case Applicant = 'applicant';
    case Student = 'student';
    case Lecturer = 'lecturer';
    case Accountant = 'accountant';
    case Sao = 'sao';
    case Admin = 'admin';

    /**
     * Canonical human-readable label, shared by mail copy and the admin role
     * dropdowns so the two can't drift (AUDIT.md AUD-027).
     */
    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Applicant',
            self::Student => 'Student',
            self::Lecturer => 'Lecturer',
            self::Accountant => 'Accountant',
            self::Sao => 'SAO',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Roles attached to admin-provisioned accounts (plan/context.md §4.6).
     * Soft-deleted accounts holding any of these are excluded from
     * self-service reactivation — only an admin may restore them
     * (AUDIT.md AUD-004).
     *
     * @return array<int, self>
     */
    public static function staff(): array
    {
        return [self::Lecturer, self::Accountant, self::Sao, self::Admin];
    }
}
