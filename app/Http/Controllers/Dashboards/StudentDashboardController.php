<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ProgramOffering;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    /**
     * Student dashboard: enrollment summary (matricule, programme, level,
     * status) plus the user's application history (AUDIT.md AUD-024).
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $profile = $user->studentProfile()
            ->with('programOffering.department:id,name,code')
            ->first();

        $applications = Application::query()
            ->where('user_id', $user->id)
            ->with('programOffering.department:id,name,code')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Application $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'level' => $application->level,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'decided_at' => $application->decided_at?->toIso8601String(),
                'program_offering' => $this->offering($application->programOffering),
            ]);

        return Inertia::render('dashboards/Student', [
            'profile' => $profile === null ? null : [
                'matricule' => $profile->matricule,
                'level' => $profile->level,
                'academic_year' => $profile->academic_year,
                'status' => $profile->status->value,
                'enrolled_at' => $profile->enrolled_at?->toDateString(),
                'program_offering' => $this->offering($profile->programOffering),
            ],
            'applications' => $applications,
        ]);
    }

    /**
     * Null-safe offering shape — a soft-deleted offering resolves to null on
     * the default relation, and historical rows must still render.
     *
     * @return array{degree_program: string, department: array{name: string, code: string}|null}|null
     */
    private function offering(?ProgramOffering $offering): ?array
    {
        if ($offering === null) {
            return null;
        }

        return [
            'degree_program' => $offering->degree_program->value,
            'department' => $offering->department === null ? null : [
                'name' => $offering->department->name,
                'code' => $offering->department->code,
            ],
        ];
    }
}
