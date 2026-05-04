<?php

namespace App\Http\Controllers\Admin\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\References\DocumentTypeStoreRequest;
use App\Http\Requests\Admin\References\DocumentTypeUpdateRequest;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of document types.
     */
    public function index(): Response
    {
        return Inertia::render('admin/references/DocumentTypes', [
            'documentTypes' => DocumentType::query()
                ->withCount('levelCredentialRequirements')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'description']),
        ]);
    }

    /**
     * Store a newly created document type.
     */
    public function store(DocumentTypeStoreRequest $request): RedirectResponse
    {
        DocumentType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document type created.')]);

        return back();
    }

    /**
     * Update the specified document type.
     */
    public function update(DocumentTypeUpdateRequest $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document type updated.')]);

        return back();
    }

    /**
     * Soft-delete the specified document type. Refuses if it is still referenced by level requirements.
     */
    public function destroy(DocumentType $documentType): RedirectResponse
    {
        if ($documentType->levelCredentialRequirements()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete: this document type is still referenced by level requirements.'),
            ]);

            return back();
        }

        $documentType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document type deleted.')]);

        return back();
    }
}
