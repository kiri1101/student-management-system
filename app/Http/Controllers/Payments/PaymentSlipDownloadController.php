<?php

namespace App\Http\Controllers\Payments;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentSlipDownloadController extends Controller
{
    /**
     * Stream a payment slip to the student who reported it or to an accountant
     * /admin reviewing it (mirrors the ApplicationDocument download auth,
     * AUDIT.md AUD-030 — same default disk the upload path writes to).
     */
    public function __invoke(Request $request, PaymentSubmission $payment): StreamedResponse
    {
        $user = $request->user();

        $isOwner = $payment->studentProfile()->where('user_id', $user->id)->exists();
        $canReview = $user->hasAnyRole([RoleName::Accountant, RoleName::Admin]);

        abort_unless($isOwner || $canReview, 403);

        return Storage::download(
            $payment->slip_path,
            $payment->slip_original_filename,
        );
    }
}
