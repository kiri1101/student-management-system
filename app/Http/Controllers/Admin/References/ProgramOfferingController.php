<?php

namespace App\Http\Controllers\Admin\References;

use App\Enums\DegreeProgram;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\ProgramOfferingStoreRequest;
use App\Http\Requests\Admin\References\ProgramOfferingUpdateRequest;
use App\Models\Department;
use App\Models\ProgramOffering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramOfferingController extends Controller
{
    /**
     * Display a listing of program offerings with department + child counts,
     * optionally including trashed rows so they can be restored (AUD-021).
     */
    public function index(Request $request): Response
    {
        $withTrashed = $request->boolean('trashed');

        return Inertia::render('admin/references/ProgramOfferings', [
            'offerings' => ProgramOffering::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->with('department:id,name,code')
                ->withCount('levelCredentialRequirements')
                ->orderBy('department_id')
                ->orderBy('degree_program')
                ->get(),
            'filters' => ['trashed' => $withTrashed],
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'degreePrograms' => collect(DegreeProgram::cases())
                ->map(fn (DegreeProgram $case): array => [
                    'value' => $case->value,
                    'label' => $case->name,
                ])
                ->values(),
        ]);
    }

    /**
     * Store a newly created program offering.
     */
    public function store(ProgramOfferingStoreRequest $request): RedirectResponse
    {
        ProgramOffering::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program offering created.')]);

        return back();
    }

    /**
     * Update the specified program offering.
     */
    public function update(ProgramOfferingUpdateRequest $request, ProgramOffering $programOffering): RedirectResponse
    {
        $programOffering->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program offering updated.')]);

        return back();
    }

    /**
     * Soft-delete the specified program offering. Refuses if it still has
     * level requirements or applications referencing it (AUDIT.md AUD-013).
     */
    public function destroy(ProgramOffering $programOffering): RedirectResponse
    {
        if ($programOffering->levelCredentialRequirements()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete: this offering still has level credential requirements.'),
            ]);

            return back();
        }

        if ($programOffering->applications()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete: applications reference this offering.'),
            ]);

            return back();
        }

        $programOffering->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program offering deleted.')]);

        return back();
    }

    /**
     * Restore a soft-deleted program offering. Refuses while its department
     * is trashed (the row would 500 every consumer). No key-conflict guard is
     * needed: the (department, degree) unique index spans trashed rows.
     */
    public function restore(ProgramOffering $programOffering): RedirectResponse
    {
        if (! $programOffering->trashed()) {
            return back();
        }

        if (! $programOffering->department()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot restore: this offering\'s department is deleted. Restore the department first.'),
            ]);

            return back();
        }

        $programOffering->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program offering restored.')]);

        return back();
    }
}
