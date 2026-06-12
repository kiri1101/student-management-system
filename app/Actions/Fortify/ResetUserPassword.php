<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * A reset against a soft-deleted account doubles as verify-first
     * reactivation: the reset token mailed to the address already proved
     * control of the mailbox, so the row is restored with the new password
     * while every role is detached — the user re-enters roleless and the SAO
     * re-attaches prior enrollment during review (plan/context.md §13,
     * AUDIT.md AUD-004). Trashed staff/admin accounts never reach this
     * action: the password broker's provider filters them out, leaving
     * admin-driven restore as their only path back.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if ($user->trashed()) {
            $this->reactivate($user, $input['password']);

            return;
        }

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }

    /**
     * Restore a soft-deleted account under a fresh password. Name and
     * `email_verified_at` keep their historical values (same mailbox, same
     * identity); roles are detached so trust is re-established through the
     * normal application/review flow, each revocation audited (AUD-028).
     */
    private function reactivate(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $roles = $user->roles()->get();

            // Quietly: RecordsAudit would write a bare Restored row, but the
            // manual one below carries the `reactivated` context — one row,
            // not two, for the same fact.
            $user->restoreQuietly();

            $user->forceFill([
                'password' => $password,
                'remember_token' => null,
            ])->save();

            $user->roles()->detach();

            foreach ($roles as $role) {
                AuditLog::record(
                    AuditAction::RoleRevoked,
                    $user,
                    changes: ['role' => $role->name->value],
                    context: ['reactivated' => true],
                    userId: $user->id,
                );
            }

            AuditLog::record(
                AuditAction::Restored,
                $user,
                context: ['reactivated' => true],
                userId: $user->id,
            );
        });
    }
}
