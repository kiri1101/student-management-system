<?php

namespace App\Http\Controllers\Sao;

use App\Actions\Sao\DecideApplicationAction;
use App\Actions\Sao\RestorePriorEnrollment;
use App\Actions\Sao\TriageApplicationAction;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sao\DecideApplicationRequest;
use App\Http\Requests\Sao\RestorePriorEnrollmentRequest;
use App\Http\Requests\Sao\TriageApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationReviewController extends Controller
{
    /**
     * Whitelist for the index endpoint's `sort_field` query param. Anything
     * outside this list silently falls back to the default sort.
     *
     * @var list<string>
     */
    private const SORT_FIELDS = ['submitted_at', 'created_at', 'level'];

    public function dashboard(): Response
    {
        $counts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = collect(ApplicationStatus::cases())
            ->mapWithKeys(fn (ApplicationStatus $status): array => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();

        return Inertia::render('dashboards/Sao', [
            'statusCounts' => $statusCounts,
        ]);
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(array_column(ApplicationStatus::cases(), 'value'))],
            'sort_field' => ['nullable', 'string', Rule::in(self::SORT_FIELDS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'rows' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Default filter = the actionable interim trio; the endpoint accepts
        // any ApplicationStatus value so SAOs can also browse decided rows.
        $statuses = $validated['status'] ?? array_map(
            fn (ApplicationStatus $status): string => $status->value,
            Application::INTERIM_STATUSES,
        );
        $sortField = $validated['sort_field'] ?? 'submitted_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $rows = $validated['rows'] ?? 15;

        $paginator = Application::query()
            ->with('programOffering.department:id,name,code')
            ->whereIn('status', $statuses)
            ->orderBy($sortField, $sortOrder)
            ->paginate($rows)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (Application $application): array => [
            'id' => $application->id,
            'status' => $application->status->value,
            'level' => $application->level,
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
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

        return Inertia::render('sao/applications/Index', [
            'applications' => $paginator,
            'filters' => [
                'status' => $statuses,
                'sort_field' => $sortField,
                'sort_order' => $sortOrder,
                'rows' => $rows,
            ],
            'statusOptions' => collect(ApplicationStatus::cases())
                ->map(fn (ApplicationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(Application $application): Response
    {
        $application->load([
            'applicant:id,name,email',
            'programOffering.department:id,name,code',
            'documents.documentType:id,name,code',
            'decidedBy:id,name',
        ]);

        $priorProfiles = StudentProfile::withTrashed()
            ->where('user_id', $application->user_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                'id' => $profile->id,
                'matricule' => $profile->matricule,
                'level' => $profile->level,
                'academic_year' => $profile->academic_year,
                'status' => $profile->status->value,
                'enrolled_at' => $profile->enrolled_at?->toDateString(),
                'deleted_at' => $profile->deleted_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $priorApplications = Application::withTrashed()
            ->where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->whereNotIn('status', [ApplicationStatus::Draft])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Application $prior): array => [
                'id' => $prior->id,
                'status' => $prior->status->value,
                'submitted_at' => $prior->submitted_at?->toIso8601String(),
                'decided_at' => $prior->decided_at?->toIso8601String(),
                'decision_notes' => $prior->decision_notes,
            ])
            ->values()
            ->all();

        return Inertia::render('sao/applications/Review', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'is_terminal' => $application->isTerminal(),
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
                'applicant' => [
                    'id' => $application->applicant->id,
                    'name' => $application->applicant->name,
                    'email' => $application->applicant->email,
                ],
                'decided_by' => $application->decidedBy === null ? null : [
                    'id' => $application->decidedBy->id,
                    'name' => $application->decidedBy->name,
                ],
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
            'priorHistory' => [
                'profiles' => $priorProfiles,
                'applications' => $priorApplications,
            ],
        ]);
    }

    public function triage(
        TriageApplicationRequest $request,
        Application $application,
        TriageApplicationAction $action,
    ): RedirectResponse {
        $action->execute(
            $application,
            $request->statusEnum(),
            $request->input('notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application status updated.')]);

        return back();
    }

    public function decide(
        DecideApplicationRequest $request,
        Application $application,
        DecideApplicationAction $action,
    ): RedirectResponse {
        $extraContext = $request->boolean('acknowledged_prior_history')
            ? ['acknowledged_prior_history' => true]
            : [];

        $action->execute(
            $application,
            $request->statusEnum(),
            $request->input('notes'),
            $request->user(),
            $extraContext,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Decision recorded.')]);

        return redirect()->route('sao.applications.index');
    }

    public function restorePrior(
        RestorePriorEnrollmentRequest $request,
        Application $application,
        RestorePriorEnrollment $action,
    ): RedirectResponse {
        $prior = StudentProfile::withTrashed()->findOrFail($request->integer('prior_profile_id'));

        $action->execute(
            $application->applicant,
            $prior,
            $application,
            $request->user(),
            $request->input('notes'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prior enrollment restored.')]);

        return redirect()->route('sao.applications.index');
    }
}
