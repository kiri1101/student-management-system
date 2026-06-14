<?php

use App\Http\Controllers\Lecturer\CourseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:lecturer'])
    ->prefix('lecturer')
    ->name('lecturer.')
    ->group(function (): void {
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::patch('courses/{course}/plan', [CourseController::class, 'update'])->name('courses.update');
        Route::post('courses/{course}/submit', [CourseController::class, 'submit'])->name('courses.submit');
    });
