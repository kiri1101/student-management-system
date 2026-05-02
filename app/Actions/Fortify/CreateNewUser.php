<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Re-registration with a soft-deleted email reactivates the existing row:
     * the row is restored, name/password are overwritten, `email_verified_at`
     * is cleared so the standard `MustVerifyEmail` flow re-runs, and all role
     * assignments are detached. See plan/context.md §13.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $email = is_string($input['email'] ?? null) ? $input['email'] : '';

        $existing = User::withTrashed()->where('email', $email)->first();

        // When the email matches a soft-deleted row, ignore it in the unique
        // check so validation passes; the standard rule still catches active
        // duplicates with the usual 422.
        $emailRule = ($existing !== null && $existing->trashed())
            ? $this->emailRules($existing->id)
            : $this->emailRules();

        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $emailRule,
            'password' => $this->passwordRules(),
        ])->validate();

        if ($existing !== null && $existing->trashed()) {
            return DB::transaction(function () use ($existing, $input): User {
                $existing->restore();

                $existing->forceFill([
                    'name' => $input['name'],
                    'password' => Hash::make($input['password']),
                    'email_verified_at' => null,
                    'remember_token' => null,
                ])->save();

                $existing->roles()->detach();

                // Phase 5 will record an audit row here with context: ['reactivated' => true].

                return $existing->fresh();
            });
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
