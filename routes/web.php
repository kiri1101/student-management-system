<?php

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
    Route::inertia('applicant/dashboard', 'dashboards/Applicant')
        ->name('applicant.dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
