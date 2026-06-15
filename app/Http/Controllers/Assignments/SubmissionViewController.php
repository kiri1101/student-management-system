<?php

namespace App\Http\Controllers\Assignments;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubmissionViewController extends Controller
{
    /**
     * MIME types safe to render inline on the app origin. Anything else is
     * refused (415) rather than served, so an unexpectedly-stored type can never
     * execute as script/markup against the viewer's session (AUD: stored XSS via
     * inline file serving). Mirrors the upload allowlist in
     * StoreAssignmentSubmissionRequest.
     *
     * @var list<string>
     */
    private const INLINE_SAFE_MIMES = ['application/pdf', 'image/png', 'image/jpeg'];

    /**
     * Stream a submission inline (browser renders PDFs/images in place) to the
     * student who submitted it, the lecturer assigned to its course, or an admin.
     * Mirrors SubmissionDownloadController's auth and default-disk usage exactly;
     * only the Content-Disposition differs (inline instead of attachment).
     */
    public function __invoke(Request $request, AssignmentSubmission $submission): BinaryFileResponse
    {
        $user = $request->user();

        $submission->loadMissing('assignment.course', 'studentProfile');

        $isOwner = $submission->studentProfile?->user_id === $user->id;
        $isCourseLecturer = $user->lecturerProfile !== null
            && $submission->assignment->course->lecturer_profile_id === $user->lecturerProfile->id;
        $isAdmin = $user->hasRole(RoleName::Admin);

        abort_unless($isOwner || $isCourseLecturer || $isAdmin, 403);
        abort_unless(in_array($submission->mime_type, self::INLINE_SAFE_MIMES, true), 415);

        // Force the validated MIME (never sniff), sandbox the response, and strip
        // header-breaking characters from the filename so it cannot be rendered
        // as HTML/SVG or used for header injection.
        $filename = str_replace(['"', "\r", "\n"], '', $submission->original_filename);

        return response()->file(Storage::path($submission->file_path), [
            'Content-Type' => $submission->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox; img-src 'self'; object-src 'self'",
        ]);
    }
}
