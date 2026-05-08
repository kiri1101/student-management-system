<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Number of recent admissions surfaced on the dashboard. Trashed profiles
     * are intentionally excluded — withdrawn enrollments live in the audit log.
     */
    private const RECENT_ADMISSIONS_LIMIT = 5;

    public function __invoke(): Response
    {
        return Inertia::render('dashboards/Admin', [
            'totals' => [
                'users' => User::query()->count(),
                'applications' => Application::query()->count(),
                'student_profiles' => StudentProfile::query()->count(),
            ],
            'usersByRole' => $this->usersByRole(),
            'applicationsByStatus' => $this->applicationsByStatus(),
            'recentAdmissions' => $this->recentAdmissions(),
        ]);
    }

    /**
     * @return list<array{role: string, label: string, count: int}>
     */
    private function usersByRole(): array
    {
        $counts = Role::query()
            ->withCount('users')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Role $role): array => [
                $role->name->value => $role->users_count,
            ]);

        return collect(RoleName::cases())
            ->map(fn (RoleName $role): array => [
                'role' => $role->value,
                'label' => $role->name,
                'count' => (int) ($counts[$role->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function applicationsByStatus(): array
    {
        $counts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ApplicationStatus::cases())
            ->map(fn (ApplicationStatus $status): array => [
                'status' => $status->value,
                'label' => $status->name,
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAdmissions(): array
    {
        return StudentProfile::query()
            ->with([
                'user:id,name,email',
                'programOffering:id,department_id,degree_program',
                'programOffering.department:id,name,code',
            ])
            ->orderByDesc('id')
            ->limit(self::RECENT_ADMISSIONS_LIMIT)
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                'id' => $profile->id,
                'matricule' => $profile->matricule,
                'level' => $profile->level,
                'academic_year' => $profile->academic_year,
                'enrolled_at' => $profile->enrolled_at?->toDateString(),
                'user' => [
                    'id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'email' => $profile->user->email,
                ],
                'program_offering' => [
                    'id' => $profile->programOffering->id,
                    'degree_program' => $profile->programOffering->degree_program->value,
                    'department' => [
                        'id' => $profile->programOffering->department->id,
                        'name' => $profile->programOffering->department->name,
                        'code' => $profile->programOffering->department->code,
                    ],
                ],
            ])
            ->values()
            ->all();
    }
}
