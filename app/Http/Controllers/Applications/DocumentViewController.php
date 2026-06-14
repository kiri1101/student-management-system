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

        return response()->file(
            Storage::path($document->file_path),
            ['Content-Disposition' => 'inline; filename="'.addslashes($document->original_filename).'"'],
        );
    }
}
