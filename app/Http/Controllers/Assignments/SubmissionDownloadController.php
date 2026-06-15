<?php

namespace App\Http\Controllers\Assignments;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionDownloadController extends Controller
{
    /**
     * Stream a submission to the student who submitted it, the lecturer assigned
     * to its course, or an admin (mirrors PaymentSlipDownloadController — same
     * default disk the upload path writes to).
     */
    public function __invoke(Request $request, AssignmentSubmission $submission): StreamedResponse
    {
        $user = $request->user();

        $submission->loadMissing('assignment.course', 'studentProfile');

        $isOwner = $submission->studentProfile?->user_id === $user->id;
        $isCourseLecturer = $user->lecturerProfile !== null
            && $submission->assignment->course->lecturer_profile_id === $user->lecturerProfile->id;
        $isAdmin = $user->hasRole(RoleName::Admin);

        abort_unless($isOwner || $isCourseLecturer || $isAdmin, 403);

        return Storage::download(
            $submission->file_path,
            $submission->original_filename,
        );
    }
}
