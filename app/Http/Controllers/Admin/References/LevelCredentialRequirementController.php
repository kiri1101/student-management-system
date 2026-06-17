<?php

namespace App\Http\Controllers\Admin\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\LevelCredentialRequirementStoreRequest;
use App\Http\Requests\Admin\References\LevelCredentialRequirementUpdateRequest;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use App\Services\ReferenceDataCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LevelCredentialRequirementController extends Controller
{
    public function __construct(private readonly ReferenceDataCache $cache) {}

    /**
     * Display a listing of level credential requirements, optionally including
     * trashed rows so they can be restored (AUDIT.md AUD-021).
     */
    public function index(Request $request): Response
    {
        $withTrashed = $request->boolean('trashed');

        return Inertia::render('admin/references/LevelRequirements', [
            'requirements' => LevelCredentialRequirement::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->with([
                    'programOffering:id,department_id,degree_program,min_level,max_level',
                    'programOffering.department:id,name,code',
                    'documentType:id,name,code',
                ])
                ->orderBy('program_offering_id')
                ->orderBy('level')
                ->get(),
            'filters' => ['trashed' => $withTrashed],
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

        $this->cache->flush();

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

        $this->cache->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement updated.')]);

        return back();
    }

    /**
     * Soft-delete the specified level credential requirement.
     */
    public function destroy(LevelCredentialRequirement $levelCredentialRequirement): RedirectResponse
    {
        $levelCredentialRequirement->delete();

        $this->cache->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement deleted.')]);

        return back();
    }

    /**
     * Restore a soft-deleted level credential requirement. Refuses while a
     * parent is trashed (the row would render dead slots). No key-conflict
     * guard is needed: the composite unique index spans trashed rows.
     */
    public function restore(LevelCredentialRequirement $levelCredentialRequirement): RedirectResponse
    {
        if (! $levelCredentialRequirement->trashed()) {
            return back();
        }

        if (! $levelCredentialRequirement->programOffering()->exists()
            || ! $levelCredentialRequirement->documentType()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot restore: the parent offering or document type is deleted. Restore it first.'),
            ]);

            return back();
        }

        $levelCredentialRequirement->restore();

        $this->cache->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Level requirement restored.')]);

        return back();
    }
}
