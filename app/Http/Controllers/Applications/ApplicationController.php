<?php

namespace App\Http\Controllers\Applications;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    /**
     * Submit a new application. Persists the Application with status
     * `Submitted` plus an `ApplicationDocument` row per uploaded file. The
     * Inertia pages that drive this endpoint land in Phase 8.
     */
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $documentTypeIds = $request->documentTypeIdMap();
        $uploads = (array) $request->file('documents', []);

        $application = DB::transaction(function () use ($request, $documentTypeIds, $uploads): Application {
            $application = Application::create([
                'user_id' => $request->user()->id,
                'program_offering_id' => $request->integer('program_offering_id'),
                'level' => $request->integer('level'),
                'first_name' => $request->string('first_name')->toString(),
                'last_name' => $request->string('last_name')->toString(),
                'contact_email' => $request->string('contact_email')->toString(),
                'phone' => $request->string('phone')->toString(),
                'date_of_birth' => $request->date('date_of_birth'),
                'previous_institute' => $request->input('previous_institute'),
                'status' => ApplicationStatus::Submitted->value,
                'submitted_at' => now(),
            ]);

            foreach ($request->requiredDocumentCodes() as $code) {
                $file = $uploads[$code] ?? null;

                if ($file === null) {
                    continue;
                }

                $path = $file->store('applications');

                ApplicationDocument::create([
                    'application_id' => $application->id,
                    'document_type_id' => $documentTypeIds[$code],
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_at' => now(),
                ]);
            }

            return $application;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application submitted.')]);

        return redirect()->route('applicant.dashboard')->with('application', $application->id);
    }
}
