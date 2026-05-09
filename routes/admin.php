<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\References\DepartmentController;
use App\Http\Controllers\Admin\References\DocumentTypeController;
use App\Http\Controllers\Admin\References\LevelCredentialRequirementController;
use App\Http\Controllers\Admin\References\ProgramOfferingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // User management
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])
            ->withTrashed()
            ->name('users.restore');
        Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite'])->name('users.resend-invite');
        Route::patch('users/{user}/role', [UserController::class, 'changeRole'])->name('users.role');

        Route::prefix('references')
            ->name('references.')
            ->group(function (): void {

                Route::inertia('/', 'admin/references/Index')->name('index');

                // Departments
                Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
                Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
                Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
                Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

                // Program offerings
                Route::get('program-offerings', [ProgramOfferingController::class, 'index'])->name('program-offerings.index');
                Route::post('program-offerings', [ProgramOfferingController::class, 'store'])->name('program-offerings.store');
                Route::patch('program-offerings/{program_offering}', [ProgramOfferingController::class, 'update'])->name('program-offerings.update');
                Route::delete('program-offerings/{program_offering}', [ProgramOfferingController::class, 'destroy'])->name('program-offerings.destroy');

                // Document types
                Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
                Route::post('document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
                Route::patch('document-types/{document_type}', [DocumentTypeController::class, 'update'])->name('document-types.update');
                Route::delete('document-types/{document_type}', [DocumentTypeController::class, 'destroy'])->name('document-types.destroy');

                // Level credential requirements
                Route::get('level-requirements', [LevelCredentialRequirementController::class, 'index'])->name('level-requirements.index');
                Route::post('level-requirements', [LevelCredentialRequirementController::class, 'store'])->name('level-requirements.store');
                Route::patch('level-requirements/{level_credential_requirement}', [LevelCredentialRequirementController::class, 'update'])->name('level-requirements.update');
                Route::delete('level-requirements/{level_credential_requirement}', [LevelCredentialRequirementController::class, 'destroy'])->name('level-requirements.destroy');
            });
    });
