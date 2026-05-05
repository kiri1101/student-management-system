<?php

use App\Http\Controllers\Applications\ApplicationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::inertia('admin/dashboard', 'dashboards/Admin')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::inertia('sao/dashboard', 'dashboards/Sao')
        ->middleware('role:sao')
        ->name('sao.dashboard');

    Route::inertia('accountant/dashboard', 'dashboards/Accountant')
        ->middleware('role:accountant')
        ->name('accountant.dashboard');

    Route::inertia('lecturer/dashboard', 'dashboards/Lecturer')
        ->middleware('role:lecturer')
        ->name('lecturer.dashboard');

    Route::inertia('student/dashboard', 'dashboards/Student')
        ->middleware('role:student')
        ->name('student.dashboard');

    // Applicant dashboard intentionally has no role guard — it's the roleless fallback
    // a freshly-registered (or reactivated) user lands on before applying.
    Route::get('applicant/dashboard', [ApplicationController::class, 'dashboard'])->name('applicant.dashboard');

    Route::get('application/new', [ApplicationController::class, 'create'])->name('application.create');
    Route::post('application', [ApplicationController::class, 'store'])->name('application.store');
    Route::get('application/{application}', [ApplicationController::class, 'show'])->name('application.show');

    Route::prefix('api/v1')->name('api.v1.')->group(function (): void {
        Route::get('program-offerings', [ApplicationController::class, 'offerings'])->name('program-offerings.index');
        Route::get('level-requirements', [ApplicationController::class, 'levelRequirements'])->name('level-requirements.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
