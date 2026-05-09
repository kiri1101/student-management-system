<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\WritesRoleProfile;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeUserRoleAction
{
    use WritesRoleProfile;

    /**
     * Detach every staff role currently on $user, soft-delete the previous
     * role's profile, and attach the new role + profile in one transaction.
     *
     * Both `RoleRevoked` (one per detached role) and `RoleAssigned` are
     * recorded so the audit trail captures the full role transition.
     *
     * @param  array<string, mixed>  $profilePayload
     */
    public function execute(User $user, RoleName $newRole, array $profilePayload = []): User
    {
        if (! in_array($newRole, [RoleName::Lecturer, RoleName::Accountant, RoleName::Sao, RoleName::Admin], true)) {
            throw new RuntimeException("Role [{$newRole->value}] cannot be assigned via the admin user-management flow.");
        }

        return DB::transaction(function () use ($user, $newRole, $profilePayload): User {
            $previousRoles = $user->roles()->pluck('name')->all();

            foreach ($previousRoles as $rawRole) {
                $previous = $rawRole instanceof RoleName ? $rawRole : RoleName::from((string) $rawRole);

                if ($previous === $newRole) {
                    continue;
                }

                $user->removeRole($previous);

                AuditLog::record(
                    AuditAction::RoleRevoked,
                    $user,
                    ['role' => $previous->value],
                );
            }

            $this->softDeleteOtherStaffProfiles($user, $newRole);

            if (! $user->hasRole($newRole)) {
                $user->assignRole($newRole);

                AuditLog::record(
                    AuditAction::RoleAssigned,
                    $user,
                    ['role' => $newRole->value],
                );
            }

            $this->writeProfile($user, $newRole, $profilePayload);

            return $user->fresh();
        });
    }
}
