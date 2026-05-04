<?php

namespace App\Http\Controllers\Admin\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\LevelCredentialRequirementStoreRequest;
use App\Http\Requests\Admin\References\LevelCredentialRequirementUpdateRequest;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LevelCredentialRequirementController extends Controller
{
    /**
     * Display a listing of level credential requirements.
     */
    public function index(): Response
    {
        return Inertia::render('admin/references/LevelRequirements', [
            'requirements' => LevelCredentialRequirement::query()
                ->with([
                    'programOffering:id,department_id,degree_program,min_level,max_level',
                    'programOffering.department:id,name,code',
                    'documentType:id,name,code',
                ])
                ->orderBy('program_offering_id')
                ->orderBy('level')
                ->get(),
            'offerings' => ProgramOffering::query()
                ->with('department:id,name,code')
                ->orderBy('department_id')
                ->orderBy('degree_program')
                ->get(['id', 'department_id', 'degree_program', 'min_level', 'max_level']),
            'documentTypes' => DocumentType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Store a newly created level credential requirement.
     */
    public function store(LevelCredentialRequirementStoreRequest $request): RedirectResponse
    {
        LevelCredentialRequirement::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement created.')]);

        return back();
    }

    /**
     * Update the specified level credential requirement.
     */
    public function update(
        LevelCredentialRequirementUpdateRequest $request,
        LevelCredentialRequirement $levelCredentialRequirement,
    ): RedirectResponse {
        $levelCredentialRequirement->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement updated.')]);

        return back();
    }

    /**
     * Soft-delete the specified level credential requirement.
     */
    public function destroy(LevelCredentialRequirement $levelCredentialRequirement): RedirectResponse
    {
        $levelCredentialRequirement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement deleted.')]);

        return back();
    }
}
