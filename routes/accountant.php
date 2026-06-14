<?php

use App\Http\Controllers\Accountant\DeferralController;
use App\Http\Controllers\Accountant\PaymentController;
use App\Http\Controllers\Dashboards\AccountantDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:accountant,admin'])
    ->prefix('accountant')
    ->name('accountant.')
    ->group(function (): void {
        Route::get('dashboard', AccountantDashboardController::class)->name('dashboard');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/validate', [PaymentController::class, 'validatePayment'])->name('payments.validate');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('deferrals', [DeferralController::class, 'index'])->name('deferrals.index');
        Route::get('deferrals/{deferral}', [DeferralController::class, 'show'])->name('deferrals.show');
        Route::post('deferrals/{deferral}/approve', [DeferralController::class, 'approve'])->name('deferrals.approve');
        Route::post('deferrals/{deferral}/reject', [DeferralController::class, 'reject'])->name('deferrals.reject');
    });
