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
