<?php

namespace App\Http\Controllers\Applications;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentViewController extends Controller
{
    /**
     * MIME types safe to render inline on the app origin. Anything else is
     * refused (415) rather than served, so an unexpectedly-stored type can never
     * execute as script/markup against the viewer's session (AUD: stored XSS via
     * inline file serving). Mirrors the upload allowlist in StoreApplicationRequest.
     *
     * @var list<string>
     */
    private const INLINE_SAFE_MIMES = ['application/pdf', 'image/png', 'image/jpeg'];

    /**
     * Stream an application document inline (browser renders PDFs/images in place)
     * to the owning applicant or to a reviewing SAO/admin. Mirrors
     * DocumentDownloadController's auth and default-disk usage exactly; only the
     * Content-Disposition differs (inline instead of attachment).
     */
    public function __invoke(
        Request $request,
        Application $application,
        ApplicationDocument $document,
    ): BinaryFileResponse {
        abort_if($document->application_id !== $application->id, 404);

        $user = $request->user();
        $canView = $user->id === $application->user_id
            || $user->hasAnyRole([RoleName::Sao, RoleName::Admin]);

        abort_unless($canView, 403);
        abort_unless(in_array($document->mime_type, self::INLINE_SAFE_MIMES, true), 415);

        // Force the validated MIME (never sniff), sandbox the response, and strip
        // header-breaking characters from the filename so it cannot be rendered
        // as HTML/SVG or used for header injection.
        $filename = str_replace(['"', "\r", "\n"], '', $document->original_filename);

        return response()->file(Storage::path($document->file_path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox; img-src 'self'; object-src 'self'",
        ]);
    }
}
