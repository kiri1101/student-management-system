<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LecturerDashboardController extends Controller
{
    /**
     * Lecturer dashboard: profile card while the course-management module is
     * pending (AUDIT.md AUD-024; full dashboard arrives with backlog item B3).
     */
    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->lecturerProfile()
            ->with('department:id,name,code')
            ->first();

        return Inertia::render('dashboards/Lecturer', [
            'profile' => $profile === null ? null : [
                'specialization' => $profile->specialization,
                'hired_at' => $profile->hired_at?->toDateString(),
                'department' => $profile->department === null ? null : [
                    'name' => $profile->department->name,
                    'code' => $profile->department->code,
                ],
            ],
        ]);
    }
}
