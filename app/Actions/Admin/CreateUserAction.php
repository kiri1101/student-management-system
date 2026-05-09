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
     *     profile?: array<string, mixed>,
     * }  $input
     */
    public function execute(array $input): User
    {
        $user = DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make(Str::random(64)),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            // User model intentionally does not use RecordsAudit, so admin
            // provisioning records its own Created entry to keep the audit
            // trail complete.
            AuditLog::record(
                AuditAction::Created,
                $user,
                ['attributes' => ['name' => $user->name, 'email' => $user->email]],
            );

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
