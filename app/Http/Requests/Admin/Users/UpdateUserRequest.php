<?php

namespace App\Http\Requests\Admin\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Mirror StoreUserRequest: employee IDs are stored lowercase to match the
     * canonicalized login identifier.
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!stm-)[a-z0-9][a-z0-9._-]*$/',
                Rule::unique(User::class, 'employee_id')->ignore($this->route('user')),
            ],

            'profile' => ['array'],

            // Lecturer
            'profile.department_id' => ['nullable', 'integer', 'exists:departments,id'],
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
            'employee_id.unique' => 'This employee ID is already assigned to another user.',
            'employee_id.regex' => 'Employee IDs may only contain letters, digits, dots, dashes and underscores, and may not start with "stm-".',
        ];
    }

    public function employeeId(): ?string
    {
        $value = $this->input('employee_id');

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayloadFor(RoleName $role): array
    {
        return match ($role) {
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
