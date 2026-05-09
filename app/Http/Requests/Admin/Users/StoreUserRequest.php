<?php

namespace App\Http\Requests\Admin\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Roles an admin is allowed to provision via the user-management UI.
     * Applicants self-register; Students come from SAO admission.
     *
     * @var array<int, RoleName>
     */
    public const CREATABLE_ROLES = [
        RoleName::Lecturer,
        RoleName::Accountant,
        RoleName::Sao,
        RoleName::Admin,
    ];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $roleValues = array_map(fn (RoleName $role): string => $role->value, self::CREATABLE_ROLES);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Includes soft-deleted rows on purpose: a trashed match means
                // the admin should restore the prior user, not duplicate it.
                Rule::unique(User::class, 'email'),
            ],
            'role' => ['required', 'string', Rule::in($roleValues)],

            'profile' => ['array'],

            // Lecturer
            'profile.department_id' => [
                Rule::requiredIf(fn (): bool => $this->input('role') === RoleName::Lecturer->value),
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'profile.specialization' => ['nullable', 'string', 'max:255'],
            'profile.hired_at' => ['nullable', 'date', 'before_or_equal:today'],

            // Accountant
            'profile.bank_desk' => ['nullable', 'string', 'max:255'],
            'profile.cashier_window' => ['nullable', 'string', 'max:255'],

            // SAO
            'profile.scope' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists. If they have been deactivated, restore them from the Users list instead of creating a new account.',
        ];
    }

    public function role(): RoleName
    {
        /** @var string $value */
        $value = $this->input('role');

        return RoleName::from($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(): array
    {
        return match ($this->role()) {
            RoleName::Lecturer => array_filter([
                'department_id' => $this->input('profile.department_id'),
                'specialization' => $this->input('profile.specialization'),
                'hired_at' => $this->input('profile.hired_at'),
            ], fn ($value): bool => $value !== null && $value !== ''),
            RoleName::Accountant => array_filter([
                'bank_desk' => $this->input('profile.bank_desk'),
                'cashier_window' => $this->input('profile.cashier_window'),
            ], fn ($value): bool => $value !== null && $value !== ''),
            RoleName::Sao => array_filter([
                'scope' => $this->input('profile.scope'),
            ], fn ($value): bool => $value !== null && $value !== ''),
            default => [],
        };
    }
}
