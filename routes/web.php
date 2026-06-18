<?php

use App\Http\Controllers\Applications\ApplicationController;
use App\Http\Controllers\Applications\DocumentDownloadController;
use App\Http\Controllers\Applications\DocumentViewController;
use App\Http\Controllers\Assignments\SubmissionDownloadController;
use App\Http\Controllers\Assignments\SubmissionViewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dashboards\LecturerDashboardController;
use App\Http\Controllers\Dashboards\StudentDashboardController;
use App\Http\Controllers\Payments\PaymentSlipDownloadController;
use App\Http\Controllers\Payments\PaymentSlipViewController;
use App\Http\Controllers\Receipts\VerifyReceiptController;
use App\Http\Controllers\StandingController;
use Illuminate\Support\Facades\Route;

// The landing page is the auth funnel: every request to `/` (any verb) is
// redirected to the login screen. Authenticated users then bounce on to their
// role dashboard via Fortify's guest middleware on the login route.
Route::redirect('/', '/login')->name('home');

// Public, unauthenticated school-receipt verification (#16). Throttled to slow
// enumeration; the page reveals only the identity a receipt is bound to.
Route::get('receipts/verify/{receipt_number}', VerifyReceiptController::class)
    ->middleware('throttle:lookups')
    ->name('receipts.verify');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('lecturer/dashboard', LecturerDashboardController::class)
        ->middleware('role:lecturer')
        ->name('lecturer.dashboard');

    Route::get('student/dashboard', StudentDashboardController::class)
        ->middleware('role:student')
        ->name('student.dashboard');

    // Applicant dashboard intentionally has no role guard — it's the roleless fallback
    // a freshly-registered (or reactivated) user lands on before applying.
    Route::get('applicant/dashboard', [ApplicationController::class, 'dashboard'])->name('applicant.dashboard');

    Route::get('application/new', [ApplicationController::class, 'create'])->name('application.create');
    Route::post('application', [ApplicationController::class, 'store'])->name('application.store');
    Route::get('application/{application}', [ApplicationController::class, 'show'])->name('application.show');

    Route::get('applications/{application}/documents/{document}/download', DocumentDownloadController::class)
        ->scopeBindings()
        ->middleware('throttle:lookups')
        ->name('application.documents.download');

    // Inline sibling of the download route — same auth/binding, renders the file
    // in the browser (Content-Disposition: inline) instead of forcing a download.
    Route::get('applications/{application}/documents/{document}/view', DocumentViewController::class)
        ->scopeBindings()
        ->middleware('throttle:lookups')
        ->name('application.documents.view');

    // Payment slips are reachable by the reporting student and by reviewing
    // accountants/admins; the controller enforces ownership/role itself.
    Route::get('payments/{payment}/slip', PaymentSlipDownloadController::class)
        ->middleware('throttle:lookups')
        ->name('payments.slip');

    // Inline sibling of the slip download route — same auth, renders in-browser.
    Route::get('payments/{payment}/slip/view', PaymentSlipViewController::class)
        ->middleware('throttle:lookups')
        ->name('payments.slip.view');

    // Assignment submissions are reachable by the submitting student, the course's
    // lecturer, and admins; the controller enforces ownership/role itself.
    Route::get('assignment-submissions/{submission}', SubmissionDownloadController::class)
        ->middleware('throttle:lookups')
        ->name('assignments.submission.download');

    // Inline sibling of the submission download route — same auth, renders in-browser.
    Route::get('assignment-submissions/{submission}/view', SubmissionViewController::class)
        ->middleware('throttle:lookups')
        ->name('assignments.submission.view');

    // Staff payment-standing lookup (#8): reachable by exam/gate-facing staff.
    Route::get('standing', StandingController::class)
        ->middleware('role:sao,accountant,admin')
        ->name('standing.check');

    Route::prefix('api/v1')->name('api.v1.')->middleware('throttle:lookups')->group(function (): void {
        Route::get('program-offerings', [ApplicationController::class, 'offerings'])->name('program-offerings.index');
        Route::get('level-requirements', [ApplicationController::class, 'levelRequirements'])->name('level-requirements.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/sao.php';
require __DIR__.'/lecturer.php';
require __DIR__.'/student.php';
require __DIR__.'/accountant.php';
