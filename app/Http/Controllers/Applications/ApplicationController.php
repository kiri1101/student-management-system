<?php

namespace App\Http\Controllers\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\ReferenceDataCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ApplicationController extends Controller
{
    /**
     * Upper bound on the applicant dashboard list. A single applicant realistically
     * holds a handful of applications; this caps the one otherwise-unbounded `get()`
     * so the prop can never balloon. The table paginates client-side.
     */
    private const MAX_DASHBOARD_APPLICATIONS = 50;

    /**
     * Applicant dashboard. Lists the authenticated user's applications and
     * surfaces the CTA to start a new one.
     */
    public function dashboard(Request $request): Response
    {
        $applications = Application::query()
            ->where('user_id', $request->user()->id)
            ->with('programOffering.department:id,name,code')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit(self::MAX_DASHBOARD_APPLICATIONS)
            ->get()
            ->map(fn (Application $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'level' => $application->level,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'created_at' => $application->created_at?->toIso8601String(),
                'program_offering' => [
                    'id' => $application->programOffering->id,
                    'degree_program' => $application->programOffering->degree_program->value,
                    'department' => [
                        'id' => $application->programOffering->department->id,
                        'name' => $application->programOffering->department->name,
                        'code' => $application->programOffering->department->code,
                    ],
                ],
            ]);

        return Inertia::render('dashboards/Applicant', [
            'applications' => $applications,
        ]);
    }

    /**
     * Show the application form. Sends the reference data the cascading
     * dropdowns depend on; the level-credential lookup is fetched on demand.
     */
    public function create(ReferenceDataCache $cache): Response
    {
        return Inertia::render('applicant/applications/Create', [
            'degreePrograms' => collect(DegreeProgram::cases())
                ->map(fn (DegreeProgram $case): array => [
                    'value' => $case->value,
                    'label' => $case->name,
                ])
                ->values(),
            'departments' => $cache->departments(),
            'alwaysRequiredDocumentTypes' => $cache->protectedDocumentTypes(),
        ]);
    }

    /**
     * Display a single application owned by the current user.
     */
    public function show(Request $request, Application $application): Response
    {
        abort_if($application->user_id !== $request->user()->id, 403);

        $application->load([
            'programOffering.department:id,name,code',
            'documents.documentType:id,name,code',
        ]);

        return Inertia::render('applicant/applications/Show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'level' => $application->level,
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'contact_email' => $application->contact_email,
                'phone' => $application->phone,
                'date_of_birth' => $application->date_of_birth?->toDateString(),
                'previous_institute' => $application->previous_institute,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'decided_at' => $application->decided_at?->toIso8601String(),
                'decision_notes' => $application->decision_notes,
                'program_offering' => [
                    'id' => $application->programOffering->id,
                    'degree_program' => $application->programOffering->degree_program->value,
                    'department' => [
                        'id' => $application->programOffering->department->id,
                        'name' => $application->programOffering->department->name,
                        'code' => $application->programOffering->department->code,
                    ],
                ],
                'documents' => $application->documents
                    ->map(fn (ApplicationDocument $document): array => [
                        'id' => $document->id,
                        'original_filename' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'uploaded_at' => $document->uploaded_at?->toIso8601String(),
                        'document_type' => [
                            'id' => $document->documentType->id,
                            'name' => $document->documentType->name,
                            'code' => $document->documentType->code,
                        ],
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Submit a new application. Persists the Application with status
     * `Submitted` plus an `ApplicationDocument` row per uploaded file. Also
     * auto-attaches the Applicant role on the first submission of a roleless
     * user, leaving every other case untouched.
     */
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $documentTypeIds = $request->documentTypeIdMap();
        $uploads = (array) $request->file('documents', []);

        // Files are written to disk before the transaction opens so multi-MB
        // I/O never holds the connection or the per-user lock; any failure
        // after that point removes them again (AUDIT.md AUD-009).
        $storedDocuments = [];

        try {
            foreach ($request->requiredDocumentCodes() as $code) {
                $file = $uploads[$code] ?? null;

                if ($file === null) {
                    continue;
                }

                $storedDocuments[] = [
                    'document_type_id' => $documentTypeIds[$code],
                    'file_path' => $file->store('applications'),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ];
            }

            $application = DB::transaction(function () use ($request, $storedDocuments): Application {
                // Per-user mutex: serializes concurrent submissions by the same
                // user so the one-open-application rule can't be raced past the
                // Form Request's check (AUDIT.md AUD-005).
                User::query()->whereKey($request->user()->id)->lockForUpdate()->first();

                if ($request->userHasOpenApplication()) {
                    throw ValidationException::withMessages([
                        'program_offering_id' => __('You already have an application in progress. Wait for a decision before submitting another.'),
                    ]);
                }

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

                foreach ($storedDocuments as $document) {
                    ApplicationDocument::create([
                        'application_id' => $application->id,
                        ...$document,
                        'uploaded_at' => now(),
                    ]);
                }

                $user = $request->user();

                if ($user->roles()->doesntExist()) {
                    $user->assignRole(RoleName::Applicant);
                    AuditLog::record(
                        AuditAction::RoleAssigned,
                        $user,
                        changes: ['role' => RoleName::Applicant->value],
                        userId: $user->id,
                    );
                }

                return $application;
            });
        } catch (Throwable $exception) {
            Storage::delete(array_column($storedDocuments, 'file_path'));

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application submitted.')]);

        return redirect()->route('applicant.dashboard')->with('application', $application->id);
    }

    /**
     * Cascading dropdown endpoint: list program offerings, optionally filtered
     * by degree program and department. Returned with department references
     * so the form can render labels without an extra join.
     *
     * @return array<int, array<string, mixed>>
     */
    public function offerings(Request $request, ReferenceDataCache $cache): array
    {
        $request->validate([
            'degree_program' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $degreeProgram = $request->filled('degree_program')
            ? $request->string('degree_program')->toString()
            : null;
        $departmentId = $request->filled('department_id')
            ? $request->integer('department_id')
            : null;

        return collect($cache->offerings())
            ->when($degreeProgram !== null, fn ($offerings) => $offerings->where('degree_program', $degreeProgram))
            ->when($departmentId !== null, fn ($offerings) => $offerings->where('department_id', $departmentId))
            ->values()
            ->all();
    }

    /**
     * Cascading dropdown endpoint: list required document types for a given
     * (offering, level) pair. The result feeds the dynamic upload slots on
     * the application form alongside the always-required NID + BIRTH pair.
     *
     * @return array<int, array<string, mixed>>
     */
    public function levelRequirements(Request $request, ReferenceDataCache $cache): array
    {
        $data = $request->validate([
            'offering' => ['required', 'integer'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $rules = $cache->levelRequirements()[$data['offering']] ?? [];

        return collect($rules)
            ->where('level', $data['level'])
            ->values()
            ->all();
    }
}
