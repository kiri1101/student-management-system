<?php

namespace App\Http\Controllers\Admin\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\DepartmentStoreRequest;
use App\Http\Requests\Admin\References\DepartmentUpdateRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(): Response
    {
        return Inertia::render('admin/references/Departments', [
            'departments' => Department::query()
                ->withCount('programOfferings')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'description']),
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
}
