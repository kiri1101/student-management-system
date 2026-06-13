<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Admin\Concerns\WritesRoleProfile;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StaffProfileUpdateRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StaffProfileController extends Controller
{
    use WritesRoleProfile;

    /**
     * Show the signed-in staff member their own role-profile edit form. Only
     * lecturers, accountants, and SAOs have editable fields; everyone else
     * (applicant, student, plain admin, guest) gets a 403 — there is no user
     * id in the route, so a user can only ever view their own profile.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $role = $this->editableRole($user);

        abort_if($role === null, HttpResponse::HTTP_FORBIDDEN);

        $user->loadMissing('lecturerProfile', 'accountantProfile', 'saoProfile');

        return Inertia::render('settings/StaffProfile', [
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'profile' => $this->profileFor($user, $role),
            'options' => [
                'departments' => $role === RoleName::Lecturer
                    ? Department::query()->orderBy('name')->get(['id', 'name', 'code'])
                    : [],
            ],
        ]);
    }

    /**
     * Persist the signed-in staff member's own profile fields. The request
     * authorizes (403 for non-staff) and validates only the columns the user's
     * role owns; the role itself is never read from input and so can't change.
     */
    public function update(StaffProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $role = $request->editableRole();

        // writeProfile creates the row if missing and restores a trashed one in
        // place, so a freshly-provisioned staff account can self-complete it.
        $this->writeProfile($user, $role, $request->profilePayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('staff-profile.edit');
    }

    /**
     * The first editable staff role the user holds, or null when none.
     */
    private function editableRole(?User $user): ?RoleName
    {
        if ($user === null) {
            return null;
        }

        foreach (StaffProfileUpdateRequest::EDITABLE_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The current profile values for the page, shaped per role.
     *
     * @return array<string, mixed>
     */
    private function profileFor(User $user, RoleName $role): array
    {
        return match ($role) {
            RoleName::Lecturer => [
                'department_id' => $user->lecturerProfile?->department_id,
                'specialization' => $user->lecturerProfile?->specialization,
                'hired_at' => $user->lecturerProfile?->hired_at?->toDateString(),
            ],
            RoleName::Accountant => [
                'bank_desk' => $user->accountantProfile?->bank_desk,
                'cashier_window' => $user->accountantProfile?->cashier_window,
            ],
            RoleName::Sao => [
                'scope' => $user->saoProfile?->scope,
            ],
            default => [],
        };
    }
}
