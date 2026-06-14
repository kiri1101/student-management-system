<?php

namespace App\Http\Controllers\Student;

use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StorePaymentRequest;
use App\Models\FeeSchedule;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Services\PaymentStandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PaymentController extends Controller
{
    /**
     * Upper bound on the listed submissions. A student realistically reports a
     * handful of deposits per year; this caps the one otherwise-unbounded
     * `get()`. The table paginates client-side.
     */
    private const MAX_SUBMISSIONS = 100;

    /**
     * The student's own payment record: enrollment + fee schedule, running
     * validated total, and the list of reported deposits with their status.
     */
    public function index(Request $request, PaymentStandingService $standing): Response
    {
        $profile = $request->user()->studentProfile()
            ->with('programOffering.department:id,name,code')
            ->first();

        $submissions = $profile === null ? collect() : PaymentSubmission::query()
            ->where('student_profile_id', $profile->id)
            ->with('schoolReceipt:id,payment_submission_id,receipt_number')
            ->orderByDesc('created_at')
            ->limit(self::MAX_SUBMISSIONS)
            ->get()
            ->map(fn (PaymentSubmission $submission): array => $this->submissionShape($submission));

        $validatedTotal = $profile === null ? 0 : (int) PaymentSubmission::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', PaymentStatus::Validated->value)
            ->sum('amount_xaf');

        return Inertia::render('student/payments/Index', [
            'profile' => $profile === null ? null : [
                'matricule' => $profile->matricule,
                'level' => $profile->level,
                'academic_year' => $profile->academic_year,
                'program_offering' => $profile->programOffering === null ? null : [
                    'degree_program' => $profile->programOffering->degree_program->value,
                    'department' => $profile->programOffering->department === null ? null : [
                        'name' => $profile->programOffering->department->name,
                        'code' => $profile->programOffering->department->code,
                    ],
                ],
            ],
            'schedule' => $profile === null ? null : $this->scheduleSummary($profile),
            'validatedTotal' => $validatedTotal,
            'standing' => $profile === null ? null : $standing->for($profile)->toArray(),
            'deferrals' => $profile === null ? [] : $this->deferralList($profile),
            'submissions' => $submissions->values(),
            'banks' => collect(Bank::cases())
                ->map(fn (Bank $bank): array => ['value' => $bank->value, 'label' => $bank->label()])
                ->values(),
        ]);
    }

    /**
     * The student's own deferral requests for the current year, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function deferralList(StudentProfile $profile): array
    {
        return TuitionDeferral::query()
            ->where('student_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->limit(self::MAX_SUBMISSIONS)
            ->get()
            ->map(fn (TuitionDeferral $deferral): array => [
                'id' => $deferral->id,
                'status' => $deferral->status->value,
                'academic_year' => $deferral->academic_year,
                'reason' => $deferral->reason,
                'requested_new_deadline' => $deferral->requested_new_deadline?->toDateString(),
                'new_deadline' => $deferral->new_deadline?->toDateString(),
                'decision_notes' => $deferral->decision_notes,
                'created_at' => $deferral->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Record a reported bank deposit. The slip is written to disk before the
     * row is created and cleaned up if persistence fails (mirrors the
     * application upload path, AUDIT.md AUD-009). Snapshots the student's
     * current academic year so the validated total stays year-attributable.
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'bank' => __('You need an active student enrollment before reporting a payment.'),
            ]);
        }

        $file = $request->file('slip');
        $slipPath = $file->store('payment-slips');

        try {
            PaymentSubmission::create([
                'student_profile_id' => $profile->id,
                'academic_year' => $profile->academic_year,
                'bank' => $request->string('bank')->toString(),
                'amount_xaf' => $request->integer('amount_xaf'),
                'bank_reference' => $request->string('bank_reference')->toString(),
                'slip_path' => $slipPath,
                'slip_original_filename' => $file->getClientOriginalName(),
                'slip_mime_type' => $file->getClientMimeType(),
                'status' => PaymentStatus::Submitted->value,
            ]);
        } catch (Throwable $exception) {
            Storage::delete($slipPath);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment reported. The accounts office will review it.')]);

        return back();
    }

    /**
     * The printable, QR-bearing school receipt for a validated payment. Only
     * the owning student (or an admin) may view it; an unvalidated payment has
     * no receipt and 404s.
     */
    public function receipt(Request $request, PaymentSubmission $payment): Response
    {
        $user = $request->user();
        $owns = $payment->studentProfile()->where('user_id', $user->id)->exists();

        abort_unless($owns || $user->hasRole(RoleName::Admin), 403);

        $payment->load([
            'schoolReceipt',
            'studentProfile.user:id,name',
            'studentProfile.programOffering.department:id,name,code',
        ]);

        $receipt = $payment->schoolReceipt;

        abort_if($receipt === null, 404);

        $profile = $payment->studentProfile;

        return Inertia::render('student/payments/Receipt', [
            'receipt' => [
                'receipt_number' => $receipt->receipt_number,
                'amount_xaf' => $receipt->amount_xaf,
                'issued_at' => $receipt->issued_at?->toIso8601String(),
                'academic_year' => $payment->academic_year,
                'bank_label' => $payment->bank->label(),
                'bank_reference' => $payment->bank_reference,
                'student' => [
                    'matricule' => $profile?->matricule,
                    'name' => $profile?->user?->name,
                    'level' => $profile?->level,
                    'programme' => $profile?->programOffering?->department?->name,
                ],
            ],
            'verifyUrl' => route('receipts.verify', $receipt->receipt_number),
        ]);
    }

    /**
     * @return array{id: int, status: string, bank: string, bank_label: string, amount_xaf: int, bank_reference: string, slip_original_filename: string, rejection_reason: string|null, reviewed_at: string|null, created_at: string|null, receipt_number: string|null}
     */
    private function submissionShape(PaymentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'bank' => $submission->bank->value,
            'bank_label' => $submission->bank->label(),
            'amount_xaf' => $submission->amount_xaf,
            'bank_reference' => $submission->bank_reference,
            'slip_original_filename' => $submission->slip_original_filename,
            'rejection_reason' => $submission->rejection_reason,
            'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
            'created_at' => $submission->created_at?->toIso8601String(),
            'receipt_number' => $submission->schoolReceipt?->receipt_number,
        ];
    }

    /**
     * The fee schedule the student is paying against, matched on
     * (offering, level, year). Null when no schedule has been configured yet.
     *
     * @return array{total_xaf: int, installments: array<int, array{sequence: int, label: string, amount_xaf: int, due_date: string|null}>}|null
     */
    private function scheduleSummary(StudentProfile $profile): ?array
    {
        $schedule = FeeSchedule::query()
            ->where('program_offering_id', $profile->program_offering_id)
            ->where('level', $profile->level)
            ->where('academic_year', $profile->academic_year)
            ->with('installments')
            ->first();

        if ($schedule === null) {
            return null;
        }

        return [
            'total_xaf' => $schedule->total_xaf,
            'installments' => $schedule->installments
                ->map(fn ($installment): array => [
                    'sequence' => $installment->sequence,
                    'label' => $installment->label,
                    'amount_xaf' => $installment->amount_xaf,
                    'due_date' => $installment->due_date?->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
