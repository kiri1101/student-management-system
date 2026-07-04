<?php

namespace App\Actions\Applicant;

use App\Actions\Concerns\ResolvesDocumentsRequested;
use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReplaceRejectedDocument
{
    use ResolvesDocumentsRequested;

    /**
     * Replace a rejected document's file in place. The row keeps its identity —
     * (application_id, document_type_id) is unique including trashed rows, so
     * delete-and-recreate is not an option — and returns to pending review with
     * its review metadata cleared (history stays in the audit log). Resolving
     * the last rejected document flips the application back to Submitted.
     *
     * The new file is stored before the transaction opens and removed again on
     * any failure; the replaced file is deleted only after commit (AUD-009).
     *
     * @throws ValidationException
     */
    public function execute(Application $application, ApplicationDocument $document, UploadedFile $file, User $applicant): ApplicationDocument
    {
        $newPath = $file->store('applications');
        $oldPath = null;

        try {
            $document = DB::transaction(function () use ($application, $document, $file, $applicant, $newPath, &$oldPath): ApplicationDocument {
                $application = Application::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $document = ApplicationDocument::query()
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($application->status !== ApplicationStatus::DocumentsRequested) {
                    throw ValidationException::withMessages([
                        'document' => __('Documents can only be replaced while the application is awaiting your documents.'),
                    ]);
                }

                if ($document->status !== ApplicationDocumentStatus::Rejected) {
                    throw ValidationException::withMessages([
                        'document' => __('Only rejected documents can be replaced.'),
                    ]);
                }

                $oldPath = $document->file_path;
                $previousFilename = $document->original_filename;

                $document->fill([
                    'file_path' => $newPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_at' => now(),
                    'status' => ApplicationDocumentStatus::Pending,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ])->saveQuietly();

                AuditLog::record(
                    AuditAction::DocumentResubmitted,
                    $document,
                    ['before' => $previousFilename, 'after' => $document->original_filename],
                    userId: $applicant->id,
                );

                $this->flipToSubmittedWhenResolved($application, $applicant);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null) {
            Storage::delete($oldPath);
        }

        return $document;
    }
}
