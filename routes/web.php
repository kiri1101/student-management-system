<?php

use App\Http\Controllers\Applications\ApplicationController;
use App\Http\Controllers\Applications\DocumentDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dashboards\LecturerDashboardController;
use App\Http\Controllers\Dashboards\StudentDashboardController;
use App\Http\Controllers\Payments\PaymentSlipDownloadController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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

    // Payment slips are reachable by the reporting student and by reviewing
    // accountants/admins; the controller enforces ownership/role itself.
    Route::get('payments/{payment}/slip', PaymentSlipDownloadController::class)
        ->middleware('throttle:lookups')
        ->name('payments.slip');

    Route::prefix('api/v1')->name('api.v1.')->middleware('throttle:lookups')->group(function (): void {
        Route::get('program-offerings', [ApplicationController::class, 'offerings'])->name('program-offerings.index');
        Route::get('level-requirements', [ApplicationController::class, 'levelRequirements'])->name('level-requirements.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/sao.php';
require __DIR__.'/student.php';
require __DIR__.'/accountant.php';
