<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Registration never touches existing rows: an email matching an active
     * *or* soft-deleted user fails the unique rule with the same 422, so the
     * two cases are indistinguishable and a deactivated account can never be
     * claimed anonymously (AUDIT.md AUD-004). Returning users reactivate
     * through the password-reset flow instead — see plan/context.md §13 and
     * `ResetUserPassword`.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Canonicalize the optional phone up front so the unique rule and the
        // stored value both match the form the login resolver compares against.
        $input['phone'] = self::normalizePhoneNumber(
            isset($input['phone']) && is_string($input['phone']) ? $input['phone'] : null
        );

        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        try {
            return User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent registration won the race between the unique
            // validation and the INSERT — answer with the same 422 the
            // validator would have produced (AUDIT.md AUD-017).
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }
    }
}
