<?php

use App\Http\Controllers\Student\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:student,admin'])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    });
