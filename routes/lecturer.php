<?php

use App\Http\Controllers\Lecturer\AssignmentController;
use App\Http\Controllers\Lecturer\CourseController;
use App\Http\Controllers\Lecturer\CourseResultController;
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

            // Assignments + grading live under an approved course. {assignment} and
            // {submission} are scope-bound to {course} so they can only be reached
            // through their owning course.
            Route::get('courses/{course}/assignments', [AssignmentController::class, 'index'])->name('courses.assignments.index');
            Route::post('courses/{course}/assignments', [AssignmentController::class, 'store'])->name('courses.assignments.store');
            Route::patch('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'update'])->name('courses.assignments.update');
            Route::delete('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('courses.assignments.destroy');
            Route::get('courses/{course}/assignments/{assignment}/submissions', [AssignmentController::class, 'submissions'])->name('courses.assignments.submissions');
            Route::post('courses/{course}/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('courses.assignments.grade');

            // Results (CA + exam marks) live under an approved course.
            Route::get('courses/{course}/results', [CourseResultController::class, 'index'])->name('courses.results.index');
            Route::post('courses/{course}/results', [CourseResultController::class, 'store'])->name('courses.results.store');
        });
    });
