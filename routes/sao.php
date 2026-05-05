<?php

use App\Http\Controllers\Sao\ApplicationReviewController;
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
    });
