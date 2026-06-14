<?php

namespace App\Http\Controllers\Student;

use App\Enums\DeferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDeferralRequest;
use App\Models\TuitionDeferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeferralController extends Controller
{
    /**
     * Submit a tuition deferral request for the current enrollment. One open
     * request per student-year — a new one is refused while a Requested row
     * exists, so the accountant queue can't fill with duplicates.
     */
    public function store(StoreDeferralRequest $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'reason' => __('You need an active student enrollment before requesting a deferral.'),
            ]);
        }

        $hasOpenRequest = TuitionDeferral::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year', $profile->academic_year)
            ->where('status', DeferralStatus::Requested->value)
            ->exists();

        if ($hasOpenRequest) {
            throw ValidationException::withMessages([
                'reason' => __('You already have a pending deferral request. Wait for it to be reviewed.'),
            ]);
        }

        TuitionDeferral::create([
            'student_profile_id' => $profile->id,
            'academic_year' => $profile->academic_year,
            'reason' => $request->string('reason')->toString(),
            'requested_new_deadline' => $request->date('requested_new_deadline')?->toDateString(),
            'status' => DeferralStatus::Requested->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deferral request submitted. The accounts office will review it.')]);

        return back();
    }
}
