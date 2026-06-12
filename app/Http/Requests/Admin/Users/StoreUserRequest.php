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
     * Lowercase the employee ID before validation so the unique check and the
     * stored value both match the canonicalized login identifier (Fortify's
     * `lowercase_usernames`).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('employee_id')) {
            $this->merge(['employee_id' => mb_strtolower(trim((string) $this->input('employee_id')))]);
        }
    }

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
            // No `@` (so it can never shadow the email namespace in the login
            // resolver) and no `stm-` prefix (reserved for matricules).
            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!stm-)[a-z0-9][a-z0-9._-]*$/',
                Rule::unique(User::class, 'employee_id'),
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
            'employee_id.unique' => 'This employee ID is already assigned to another user.',
            'employee_id.regex' => 'Employee IDs may only contain letters, digits, dots, dashes and underscores, and may not start with "stm-".',
        ];
    }

    public function employeeId(): ?string
    {
        $value = $this->input('employee_id');

        return $value === null || $value === '' ? null : (string) $value;
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
