<?php

namespace App\Http\Controllers\Applications;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Application $application,
        ApplicationDocument $document,
    ): StreamedResponse {
        abort_if($document->application_id !== $application->id, 404);

        $user = $request->user();
        $canDownload = $user->id === $application->user_id
            || $user->hasAnyRole([RoleName::Sao, RoleName::Admin]);

        abort_unless($canDownload, 403);

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename,
        );
    }
}
