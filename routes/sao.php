<?php

use App\Http\Controllers\Sao\ApplicationReviewController;
use App\Http\Controllers\Sao\CourseController;
use App\Http\Controllers\Sao\ResultDisputeController;
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
        Route::post('applications/{application}/documents/{document}/accept', [ApplicationReviewController::class, 'acceptDocument'])
            ->scopeBindings()
            ->name('applications.documents.accept');
        Route::post('applications/{application}/documents/{document}/reject', [ApplicationReviewController::class, 'rejectDocument'])
            ->scopeBindings()
            ->name('applications.documents.reject');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::post('courses/{course}/assign-lecturer', [CourseController::class, 'assignLecturer'])->name('courses.assignLecturer');
        Route::post('courses/{course}/approve', [CourseController::class, 'approve'])->name('courses.approve');
        Route::post('courses/{course}/reject', [CourseController::class, 'reject'])->name('courses.reject');
        Route::post('courses/{course}/publish-results', [CourseController::class, 'publishResults'])->name('courses.publishResults');

        Route::get('disputes', [ResultDisputeController::class, 'index'])->name('disputes.index');
        Route::post('disputes/{dispute}/review', [ResultDisputeController::class, 'review'])->name('disputes.review');
    });
