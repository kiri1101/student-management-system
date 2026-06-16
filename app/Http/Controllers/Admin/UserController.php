<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ChangeUserRoleAction;
use App\Actions\Admin\CreateUserAction;
use App\Actions\Admin\ImportStaffUsers;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\ChangeRoleRequest;
use App\Http\Requests\Admin\Users\ImportStaffUsersRequest;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->input('status', 'active');
        $rolesFilter = (array) $request->input('roles', []);
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when(
                $statusFilter === 'trashed',
                fn ($query) => $query->onlyTrashed(),
                fn ($query) => $statusFilter === 'all' ? $query->withTrashed() : $query,
            )
            ->when($rolesFilter !== [], function ($query) use ($rolesFilter): void {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', $rolesFilter));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
                'roles' => $user->roles->pluck('name')->values()->all(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'status' => $statusFilter,
                'roles' => $rolesFilter,
                'search' => $search,
            ],
            'options' => [
                'roles' => $this->roleOptions(),
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'trashed', 'label' => 'Deactivated'],
                    ['value' => 'all', 'label' => 'All'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'options' => [
                'roles' => $this->roleOptions(),
                'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'code']),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $action->execute([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'role' => $request->role(),
            'employee_id' => $request->employeeId(),
            'profile' => $request->profilePayload(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User created. An invitation email has been queued.'),
        ]);

        return to_route('admin.users.index');
    }

    public function importForm(): Response
    {
        return Inertia::render('admin/users/Import');
    }

    public function importPreview(ImportStaffUsersRequest $request, ImportStaffUsers $importer): Response
    {
        $preview = $importer->validate($request->file('file'));

        return Inertia::render('admin/users/Import', [
            'preview' => $preview,
        ]);
    }

    public function import(ImportStaffUsersRequest $request, ImportStaffUsers $importer): Response
    {
        $result = $importer->import($request->file('file'), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Imported :n staff; :s skipped.', [
                'n' => $result['summary']['imported'],
                's' => $result['summary']['skipped'],
            ]),
        ]);

        return Inertia::render('admin/users/Import', [
            'result' => $result,
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        $rows = [
            ImportStaffUsers::COLUMNS,
            ['Ada Lovelace', 'ada.lovelace@example.com', RoleName::Lecturer->value, 'lec-001', 'cs', 'Algorithms', '2024-09-01', '', '', ''],
            ['Grace Hopper', 'grace.hopper@example.com', RoleName::Accountant->value, 'acc-001', '', '', '', 'UBA', 'Window 3', ''],
            ['Alan Turing', 'alan.turing@example.com', RoleName::Sao->value, 'sao-001', '', '', '', '', '', 'Undergraduate'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'staff-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load('roles:id,name', 'lecturerProfile', 'accountantProfile', 'saoProfile');

        return Inertia::render('admin/users/Edit', [
            'user' => $this->transform($user),
            'options' => [
                'roles' => $this->roleOptions(),
                'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'code']),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // employee_id is deliberately outside $fillable; the admin module is
        // its only writer (AUDIT.md AUD-007).
        $user->fill(['name' => $request->string('name')->toString()])
            ->forceFill(['employee_id' => $request->employeeId()])
            ->save();

        $role = $this->primaryStaffRole($user);

        if ($role !== null) {
            $payload = $request->profilePayloadFor($role);

            if ($payload !== []) {
                $profile = $this->staffProfile($user, $role);
                $profile?->update($payload);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User updated.'),
        ]);

        return to_route('admin.users.edit', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You cannot deactivate your own account.'),
            ]);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deactivated.'),
        ]);

        return to_route('admin.users.index');
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User reactivated.'),
        ]);

        return to_route('admin.users.index');
    }

    public function resendInvite(User $user, CreateUserAction $action): RedirectResponse
    {
        $action->sendInvitation($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation re-sent.'),
        ]);

        return back();
    }

    public function changeRole(
        ChangeRoleRequest $request,
        User $user,
        ChangeUserRoleAction $action,
    ): RedirectResponse {
        if ($user->is($request->user())) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You cannot change your own role.'),
            ]);

            return back();
        }

        $action->execute($user, $request->role(), $request->profilePayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role updated.'),
        ]);

        return to_route('admin.users.edit', $user);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return collect(StoreUserRequest::CREATABLE_ROLES)
            ->map(fn (RoleName $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])
            ->all();
    }

    private function primaryStaffRole(User $user): ?RoleName
    {
        foreach ([RoleName::Lecturer, RoleName::Accountant, RoleName::Sao, RoleName::Admin] as $candidate) {
            if ($user->hasRole($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function staffProfile(User $user, RoleName $role): ?Model
    {
        return match ($role) {
            RoleName::Lecturer => $user->lecturerProfile,
            RoleName::Accountant => $user->accountantProfile,
            RoleName::Sao => $user->saoProfile,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'profiles' => [
                'lecturer' => $user->lecturerProfile === null ? null : [
                    'id' => $user->lecturerProfile->id,
                    'user_id' => $user->lecturerProfile->user_id,
                    'department_id' => $user->lecturerProfile->department_id,
                    'specialization' => $user->lecturerProfile->specialization,
                    'hired_at' => $user->lecturerProfile->hired_at?->toDateString(),
                ],
                'accountant' => $user->accountantProfile === null ? null : [
                    'id' => $user->accountantProfile->id,
                    'user_id' => $user->accountantProfile->user_id,
                    'bank_desk' => $user->accountantProfile->bank_desk,
                    'cashier_window' => $user->accountantProfile->cashier_window,
                ],
                'sao' => $user->saoProfile === null ? null : [
                    'id' => $user->saoProfile->id,
                    'user_id' => $user->saoProfile->user_id,
                    'scope' => $user->saoProfile->scope,
                ],
            ],
        ];
    }
}
