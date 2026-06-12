<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\WritesRoleProfile;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Mail\UserInvitationMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUserAction
{
    use WritesRoleProfile;

    /**
     * Create a staff/admin user, attach the role + matching profile, and send
     * an invitation email containing a single-use password-reset link.
     *
     * The user receives a randomly generated password they will never see;
     * the only path to first-login is the emailed setup link. The plaintext
     * password is intentionally never logged, audited, or stored anywhere.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     role: RoleName,
     *     employee_id?: string|null,
     *     profile?: array<string, mixed>,
     * }  $input
     */
    public function execute(array $input): User
    {
        $user = DB::transaction(function () use ($input): User {
            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make(Str::random(64)),
            ]);

            // Single save so RecordsAudit writes one Created row covering the
            // whole provisioning state. Staff are pre-verified by design (the
            // invite link is the proof of mailbox control); employee_id stays
            // outside $fillable on purpose (AUDIT.md AUD-007) — admin
            // provisioning is its only writer.
            $user->forceFill([
                'email_verified_at' => now(),
                'employee_id' => $input['employee_id'] ?? null,
            ])->save();

            $user->assignRole($input['role']);

            AuditLog::record(
                AuditAction::RoleAssigned,
                $user,
                ['role' => $input['role']->value],
            );

            $this->writeProfile($user, $input['role'], $input['profile'] ?? []);

            return $user->fresh();
        });

        $this->sendInvitation($user);

        return $user;
    }

    public function sendInvitation(User $user): void
    {
        $token = Password::broker()->createToken($user);

        Mail::to($user->email)->queue(new UserInvitationMail($user, $token));
    }
}
