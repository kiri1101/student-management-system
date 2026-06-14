<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\PaymentStandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StandingController extends Controller
{
    /**
     * Staff payment-standing lookup (#8) — a gate/exam officer (sao, accountant,
     * or admin) enters a matricule and sees whether the student is cleared for
     * exams/facilities. Standing-only and staff-gated: it reveals who is behind
     * on fees, so it is not public like the receipt verifier.
     */
    public function __invoke(Request $request, PaymentStandingService $service): Response
    {
        $validated = $request->validate([
            'matricule' => ['nullable', 'string', 'max:60'],
        ]);

        $matricule = isset($validated['matricule'])
            ? Str::lower(trim($validated['matricule']))
            : null;

        $result = null;

        if ($matricule !== null && $matricule !== '') {
            $profile = StudentProfile::query()
                ->where('matricule', $matricule)
                ->with(['user:id,name', 'programOffering.department:id,name,code'])
                ->first();

            $result = $profile === null
                ? ['found' => false]
                : [
                    'found' => true,
                    'student' => [
                        'matricule' => $profile->matricule,
                        'name' => $profile->user?->name,
                        'level' => $profile->level,
                        'academic_year' => $profile->academic_year,
                        'programme' => $profile->programOffering?->department?->name,
                    ],
                    'standing' => $service->for($profile)->toArray(),
                ];
        }

        return Inertia::render('staff/StandingCheck', [
            'query' => $matricule,
            'result' => $result,
        ]);
    }
}
