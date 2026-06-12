<?php

namespace App\Http\Controllers\Admin\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\DepartmentStoreRequest;
use App\Http\Requests\Admin\References\DepartmentUpdateRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments, optionally including trashed rows so
     * they can be restored (AUDIT.md AUD-021).
     */
    public function index(Request $request): Response
    {
        $withTrashed = $request->boolean('trashed');

        return Inertia::render('admin/references/Departments', [
            'departments' => Department::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->withCount('programOfferings')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'description', 'deleted_at']),
            'filters' => ['trashed' => $withTrashed],
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(DepartmentStoreRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return back();
    }

    /**
     * Update the specified department.
     */
    public function update(DepartmentUpdateRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return back();
    }

    /**
     * Soft-delete the specified department. Refuses if it still has program offerings.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->programOfferings()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete: this department still has program offerings.'),
            ]);

            return back();
        }

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return back();
    }

    /**
     * Restore a soft-deleted department. No key-conflict guard is needed: the
     * DB unique indexes on name/code span trashed rows, so the key cannot
     * have been re-taken while this row was deleted.
     */
    public function restore(Department $department): RedirectResponse
    {
        if (! $department->trashed()) {
            return back();
        }

        $department->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department restored.')]);

        return back();
    }
}
