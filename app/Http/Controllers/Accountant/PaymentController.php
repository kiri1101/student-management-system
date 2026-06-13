<?php

namespace App\Http\Controllers\Accountant;

use App\Actions\Accountant\ReviewPaymentAction;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountant\RejectPaymentRequest;
use App\Models\PaymentSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Whitelist for the queue's `sort_field` query param. Anything outside this
     * list silently falls back to the default sort.
     *
     * @var list<string>
     */
    private const SORT_FIELDS = ['created_at', 'amount_xaf'];

    /**
     * Payment review queue. Defaults to the actionable `Submitted` rows but
     * accepts any status so accountants can browse decided submissions.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'sort_field' => ['nullable', 'string', Rule::in(self::SORT_FIELDS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'rows' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $statuses = $validated['status'] ?? [PaymentStatus::Submitted->value];
        $sortField = $validated['sort_field'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $rows = $validated['rows'] ?? 15;

        $paginator = PaymentSubmission::query()
            ->with('studentProfile.user:id,name')
            ->whereIn('status', $statuses)
            ->orderBy($sortField, $sortOrder)
            ->paginate($rows)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (PaymentSubmission $submission): array => [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'bank' => $submission->bank->value,
            'bank_label' => $submission->bank->label(),
            'amount_xaf' => $submission->amount_xaf,
            'bank_reference' => $submission->bank_reference,
            'academic_year' => $submission->academic_year,
            'created_at' => $submission->created_at?->toIso8601String(),
            'student' => [
                'matricule' => $submission->studentProfile?->matricule,
                'name' => $submission->studentProfile?->user?->name,
            ],
        ]);

        return Inertia::render('accountant/payments/Index', [
            'payments' => $paginator,
            'filters' => [
                'status' => $statuses,
                'sort_field' => $sortField,
                'sort_order' => $sortOrder,
                'rows' => $rows,
            ],
            'statusOptions' => collect(PaymentStatus::cases())
                ->map(fn (PaymentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(PaymentSubmission $payment): Response
    {
        $payment->load([
            'studentProfile.user:id,name,email',
            'studentProfile.programOffering.department:id,name,code',
            'reviewer:id,name',
        ]);

        $profile = $payment->studentProfile;

        return Inertia::render('accountant/payments/Review', [
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'is_terminal' => $payment->isTerminal(),
                'bank' => $payment->bank->value,
                'bank_label' => $payment->bank->label(),
                'amount_xaf' => $payment->amount_xaf,
                'bank_reference' => $payment->bank_reference,
                'academic_year' => $payment->academic_year,
                'slip_original_filename' => $payment->slip_original_filename,
                'slip_mime_type' => $payment->slip_mime_type,
                'rejection_reason' => $payment->rejection_reason,
                'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'reviewed_by' => $payment->reviewer === null ? null : [
                    'id' => $payment->reviewer->id,
                    'name' => $payment->reviewer->name,
                ],
                'student' => $profile === null ? null : [
                    'matricule' => $profile->matricule,
                    'level' => $profile->level,
                    'academic_year' => $profile->academic_year,
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

    public function validatePayment(
        Request $request,
        PaymentSubmission $payment,
        ReviewPaymentAction $action,
    ): RedirectResponse {
        $action->execute($payment, PaymentStatus::Validated, null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment validated.')]);

        return redirect()->route('accountant.payments.index');
    }

    public function reject(
        RejectPaymentRequest $request,
        PaymentSubmission $payment,
        ReviewPaymentAction $action,
    ): RedirectResponse {
        $action->execute($payment, PaymentStatus::Rejected, $request->string('rejection_reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment rejected.')]);

        return redirect()->route('accountant.payments.index');
    }
}
