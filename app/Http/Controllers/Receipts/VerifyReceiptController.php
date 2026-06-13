<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\SchoolReceipt;
use Inertia\Inertia;
use Inertia\Response;

class VerifyReceiptController extends Controller
{
    /**
     * Public, unauthenticated receipt verification (#16). Re-derives the HMAC
     * from the bound identity and shows it only when authentic, so a forged or
     * tampered receipt — or one reused by a different student — is visible. An
     * unknown number and a bad signature both read as "invalid" (no oracle for
     * which receipt numbers exist).
     */
    public function __invoke(string $receiptNumber): Response
    {
        $receipt = SchoolReceipt::query()
            ->where('receipt_number', $receiptNumber)
            ->with([
                'paymentSubmission.studentProfile.user:id,name',
                'paymentSubmission.studentProfile.programOffering.department:id,name,code',
            ])
            ->first();

        $valid = $receipt !== null && $receipt->verifies();

        $profile = $receipt?->paymentSubmission?->studentProfile;

        return Inertia::render('receipts/Verify', [
            'receiptNumber' => $receiptNumber,
            'valid' => $valid,
            'receipt' => $valid ? [
                'receipt_number' => $receipt->receipt_number,
                'amount_xaf' => $receipt->amount_xaf,
                'academic_year' => $receipt->paymentSubmission->academic_year,
                'issued_at' => $receipt->issued_at?->toIso8601String(),
                'student' => [
                    'matricule' => $profile?->matricule,
                    'name' => $profile?->user?->name,
                    'level' => $profile?->level,
                    'programme' => $profile?->programOffering?->department?->name,
                ],
            ] : null,
        ]);
    }
}
