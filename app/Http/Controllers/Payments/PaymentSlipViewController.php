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
     * MIME types safe to render inline on the app origin. Anything else is
     * refused (415) rather than served, so an unexpectedly-stored type can never
     * execute as script/markup against the viewer's session (AUD: stored XSS via
     * inline file serving). Mirrors the upload allowlist in StorePaymentRequest.
     *
     * @var list<string>
     */
    private const INLINE_SAFE_MIMES = ['application/pdf', 'image/png', 'image/jpeg'];

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
        abort_unless(in_array($payment->slip_mime_type, self::INLINE_SAFE_MIMES, true), 415);

        // Force the validated MIME (never sniff), sandbox the response, and strip
        // header-breaking characters from the filename so it cannot be rendered
        // as HTML/SVG or used for header injection.
        $filename = str_replace(['"', "\r", "\n"], '', $payment->slip_original_filename);

        return response()->file(Storage::path($payment->slip_path), [
            'Content-Type' => $payment->slip_mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox; img-src 'self'; object-src 'self'",
        ]);
    }
}
