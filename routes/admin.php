<?php

use App\Http\Controllers\Admin\References\DepartmentController;
use App\Http\Controllers\Admin\References\DocumentTypeController;
use App\Http\Controllers\Admin\References\LevelCredentialRequirementController;
use App\Http\Controllers\Admin\References\ProgramOfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/references')
    ->name('admin.references.')
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
