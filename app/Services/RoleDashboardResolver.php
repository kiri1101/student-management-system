<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Resolves the dashboard a user lands on after login by walking a fixed role
 * priority. A user may hold several roles; the highest-priority match wins, and
 * a roleless user falls through to the applicant dashboard.
 */
final class RoleDashboardResolver
{
    /**
     * Role priority for post-login redirect. First match wins; roleless users
     * fall through to the applicant dashboard.
     *
     * @var array<int, array{0: RoleName, 1: string}>
     */
    public const PRIORITY = [
        [RoleName::Admin, '/admin/dashboard'],
        [RoleName::Sao, '/sao/dashboard'],
        [RoleName::Accountant, '/accountant/dashboard'],
        [RoleName::Lecturer, '/lecturer/dashboard'],
        [RoleName::Student, '/student/dashboard'],
        [RoleName::Applicant, '/applicant/dashboard'],
    ];

    /**
     * Return the dashboard path for the highest-priority role $user holds,
     * defaulting to the applicant dashboard when the user has no role. Eager-
     * loads the roles relation so the priority walk stays query-free.
     */
    public function pathFor(User $user): string
    {
        // Login happens mid-request, after the Inertia share saw a guest —
        // load once so the priority walk below stays query-free (AUD-018).
        $user->loadMissing('roles');

        foreach (self::PRIORITY as [$role, $path]) {
            if ($user->hasRole($role)) {
                return $path;
            }
        }

        return '/applicant/dashboard';
    }
}
