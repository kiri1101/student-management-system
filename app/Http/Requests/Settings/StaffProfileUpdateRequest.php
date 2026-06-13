<?php

namespace App\Http\Requests\Settings;

use App\Enums\RoleName;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StaffProfileUpdateRequest extends FormRequest
{
    /**
     * The staff roles that own an editable self-service profile, ordered by
     * precedence. Admins are intentionally excluded — they have no profile
     * fields — as are Applicant/Student, whose profiles are not user-editable.
     *
     * @var array<int, RoleName>
     */
    public const EDITABLE_ROLES = [
        RoleName::Lecturer,
        RoleName::Accountant,
        RoleName::Sao,
    ];

    /**
     * Only a signed-in staff member who owns one of the editable profiles may
     * reach this request; everyone else is rejected before validation. This is
     * the single authorization gate for the self-service profile route — there
     * is no user id in the path, so a user can only ever edit their own row.
     */
    public function authorize(): bool
    {
        return $this->editableRole() !== null;
    }

    /**
     * Validation rules mirror the admin `profile.*` rules in
     * {@see UpdateUserRequest}, but scoped to the
     * single role the signed-in user actually holds so a lecturer can never
     * smuggle accountant/SAO keys through (and vice versa).
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return match ($this->editableRole()) {
            RoleName::Lecturer => [
                'department_id' => ['nullable', 'integer', 'exists:departments,id'],
                'specialization' => ['nullable', 'string', 'max:255'],
                'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            RoleName::Accountant => [
                'bank_desk' => ['nullable', 'string', 'max:255'],
                'cashier_window' => ['nullable', 'string', 'max:255'],
            ],
            RoleName::Sao => [
                'scope' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * The first editable staff role the signed-in user holds, or null when the
     * user is a guest, applicant, student, or plain admin with no such profile.
     */
    public function editableRole(): ?RoleName
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        foreach (self::EDITABLE_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The validated profile payload, nulling empty strings so blanks clear the
     * column rather than persisting "".
     *
     * @return array<string, mixed>
     */
    public function profilePayload(): array
    {
        return array_map(
            fn ($value): mixed => $value === '' ? null : $value,
            $this->validated(),
        );
    }
}
