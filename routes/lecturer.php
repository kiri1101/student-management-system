<?php

use App\Http\Controllers\Lecturer\CourseController;
use App\Http\Controllers\Lecturer\CourseSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:lecturer'])
    ->prefix('lecturer')
    ->name('lecturer.')
    ->group(function (): void {
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::patch('courses/{course}/plan', [CourseController::class, 'update'])->name('courses.update');
        Route::post('courses/{course}/submit', [CourseController::class, 'submit'])->name('courses.submit');

        // Sessions + attendance live under an approved course. {session} is scope-bound
        // to {course} so a session can only be reached through its owning course.
        Route::scopeBindings()->group(function (): void {
            Route::get('courses/{course}/sessions', [CourseSessionController::class, 'index'])->name('courses.sessions.index');
            Route::post('courses/{course}/sessions', [CourseSessionController::class, 'store'])->name('courses.sessions.store');
            Route::patch('courses/{course}/sessions/{session}', [CourseSessionController::class, 'update'])->name('courses.sessions.update');
            Route::delete('courses/{course}/sessions/{session}', [CourseSessionController::class, 'destroy'])->name('courses.sessions.destroy');
            Route::get('courses/{course}/sessions/{session}/attendance', [CourseSessionController::class, 'attendance'])->name('courses.sessions.attendance');
            Route::post('courses/{course}/sessions/{session}/attendance', [CourseSessionController::class, 'markAttendance'])->name('courses.sessions.markAttendance');
        });
    });
