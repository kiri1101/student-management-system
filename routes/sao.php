<?php

use App\Http\Controllers\Sao\ApplicationReviewController;
use App\Http\Controllers\Sao\CourseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:sao,admin'])
    ->prefix('sao')
    ->name('sao.')
    ->group(function (): void {
        Route::get('dashboard', [ApplicationReviewController::class, 'dashboard'])->name('dashboard');

        Route::get('applications', [ApplicationReviewController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [ApplicationReviewController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/triage', [ApplicationReviewController::class, 'triage'])->name('applications.triage');
        Route::post('applications/{application}/decide', [ApplicationReviewController::class, 'decide'])->name('applications.decide');
        Route::post('applications/{application}/restore-prior', [ApplicationReviewController::class, 'restorePrior'])->name('applications.restorePrior');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::post('courses/{course}/assign-lecturer', [CourseController::class, 'assignLecturer'])->name('courses.assignLecturer');
        Route::post('courses/{course}/approve', [CourseController::class, 'approve'])->name('courses.approve');
        Route::post('courses/{course}/reject', [CourseController::class, 'reject'])->name('courses.reject');
    });
