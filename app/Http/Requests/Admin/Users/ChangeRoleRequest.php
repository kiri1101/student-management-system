<?php

namespace App\Http\Requests\Admin\Users;

use App\Enums\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRoleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $roleValues = array_map(
            fn (RoleName $role): string => $role->value,
            StoreUserRequest::CREATABLE_ROLES,
        );

        return [
            'role' => ['required', 'string', Rule::in($roleValues)],

            'profile' => ['array'],

            'profile.department_id' => [
                Rule::requiredIf(fn (): bool => $this->input('role') === RoleName::Lecturer->value),
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'profile.specialization' => ['nullable', 'string', 'max:255'],
            'profile.hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'profile.bank_desk' => ['nullable', 'string', 'max:255'],
            'profile.cashier_window' => ['nullable', 'string', 'max:255'],
            'profile.scope' => ['nullable', 'string', 'max:255'],
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
