<?php

use App\Http\Controllers\Student\AttendanceController;
use App\Http\Controllers\Student\DeferralController;
use App\Http\Controllers\Student\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:student,admin'])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

        Route::post('deferrals', [DeferralController::class, 'store'])->name('deferrals.store');

        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });
