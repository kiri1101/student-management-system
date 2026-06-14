<?php

namespace App\Http\Controllers\Accountant;

use App\Actions\Accountant\ReviewDeferralAction;
use App\Enums\DeferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountant\ApproveDeferralRequest;
use App\Http\Requests\Accountant\RejectDeferralRequest;
use App\Models\TuitionDeferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeferralController extends Controller
{
    /**
     * Whitelist for the queue's `sort_field` query param.
     *
     * @var list<string>
     */
    private const SORT_FIELDS = ['created_at'];

    /**
     * Deferral review queue. Defaults to the actionable `Requested` rows but
     * accepts any status so accountants can browse decided requests.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(array_column(DeferralStatus::cases(), 'value'))],
            'sort_field' => ['nullable', 'string', Rule::in(self::SORT_FIELDS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'rows' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $statuses = $validated['status'] ?? [DeferralStatus::Requested->value];
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $rows = $validated['rows'] ?? 15;

        $paginator = TuitionDeferral::query()
            ->with('studentProfile.user:id,name')
            ->whereIn('status', $statuses)
            ->orderBy('created_at', $sortOrder)
            ->paginate($rows)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (TuitionDeferral $deferral): array => [
            'id' => $deferral->id,
            'status' => $deferral->status->value,
            'academic_year' => $deferral->academic_year,
            'reason' => $deferral->reason,
            'requested_new_deadline' => $deferral->requested_new_deadline?->toDateString(),
            'created_at' => $deferral->created_at?->toIso8601String(),
            'student' => [
                'matricule' => $deferral->studentProfile?->matricule,
                'name' => $deferral->studentProfile?->user?->name,
            ],
        ]);

        return Inertia::render('accountant/deferrals/Index', [
            'deferrals' => $paginator,
            'filters' => [
                'status' => $statuses,
                'sort_order' => $sortOrder,
                'rows' => $rows,
            ],
            'statusOptions' => collect(DeferralStatus::cases())
                ->map(fn (DeferralStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(TuitionDeferral $deferral): Response
    {
        $deferral->load([
            'studentProfile.user:id,name,email',
            'studentProfile.programOffering.department:id,name,code',
            'reviewer:id,name',
        ]);

        $profile = $deferral->studentProfile;

        return Inertia::render('accountant/deferrals/Review', [
            'deferral' => [
                'id' => $deferral->id,
                'status' => $deferral->status->value,
                'is_terminal' => $deferral->isTerminal(),
                'academic_year' => $deferral->academic_year,
                'reason' => $deferral->reason,
                'requested_new_deadline' => $deferral->requested_new_deadline?->toDateString(),
                'new_deadline' => $deferral->new_deadline?->toDateString(),
                'decision_notes' => $deferral->decision_notes,
                'reviewed_at' => $deferral->reviewed_at?->toIso8601String(),
                'created_at' => $deferral->created_at?->toIso8601String(),
                'reviewed_by' => $deferral->reviewer === null ? null : [
                    'id' => $deferral->reviewer->id,
                    'name' => $deferral->reviewer->name,
                ],
                'student' => $profile === null ? null : [
                    'matricule' => $profile->matricule,
                    'level' => $profile->level,
                    'name' => $profile->user?->name,
                    'email' => $profile->user?->email,
                    'program_offering' => $profile->programOffering === null ? null : [
                        'degree_program' => $profile->programOffering->degree_program->value,
                        'department' => $profile->programOffering->department === null ? null : [
                            'name' => $profile->programOffering->department->name,
                            'code' => $profile->programOffering->department->code,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function approve(
        ApproveDeferralRequest $request,
        TuitionDeferral $deferral,
        ReviewDeferralAction $action,
    ): RedirectResponse {
        $action->execute(
            $deferral,
            DeferralStatus::Approved,
            $request->string('new_deadline')->toString(),
            $request->input('decision_notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deferral approved.')]);

        return redirect()->route('accountant.deferrals.index');
    }

    public function reject(
        RejectDeferralRequest $request,
        TuitionDeferral $deferral,
        ReviewDeferralAction $action,
    ): RedirectResponse {
        $action->execute(
            $deferral,
            DeferralStatus::Rejected,
            null,
            $request->string('decision_notes')->toString(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deferral rejected.')]);

        return redirect()->route('accountant.deferrals.index');
    }
}
