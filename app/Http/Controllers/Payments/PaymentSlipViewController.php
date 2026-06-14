<?php

namespace App\Http\Controllers\Payments;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentSlipViewController extends Controller
{
    /**
     * Stream a payment slip inline (browser renders PDFs/images in place) to the
     * student who reported it or to a reviewing accountant/admin. Mirrors
     * PaymentSlipDownloadController's auth and default-disk usage exactly; only
     * the Content-Disposition differs (inline instead of attachment).
     */
    public function __invoke(Request $request, PaymentSubmission $payment): BinaryFileResponse
    {
        $user = $request->user();

        $isOwner = $payment->studentProfile()->where('user_id', $user->id)->exists();
        $canReview = $user->hasAnyRole([RoleName::Accountant, RoleName::Admin]);

        abort_unless($isOwner || $canReview, 403);

        return response()->file(
            Storage::path($payment->slip_path),
            ['Content-Disposition' => 'inline; filename="'.addslashes($payment->slip_original_filename).'"'],
        );
    }
}
