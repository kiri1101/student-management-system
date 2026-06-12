<?php

namespace App\Http\Requests\Admin\References;

use App\Rules\LevelWithinOfferingRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LevelCredentialRequirementUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $id = $this->route('level_credential_requirement')?->id;

        return [
            'program_offering_id' => [
                'required', 'integer',
                Rule::exists('program_offerings', 'id')->whereNull('deleted_at'),
            ],
            'level' => [
                'required', 'integer', 'min:1', 'max:10',
                new LevelWithinOfferingRange,
            ],
            'document_type_id' => [
                'required', 'integer',
                Rule::exists('document_types', 'id')->whereNull('deleted_at'),
                Rule::unique('level_credential_requirements', 'document_type_id')
                    ->where(fn ($q) => $q
                        ->where('program_offering_id', $this->input('program_offering_id'))
                        ->where('level', $this->input('level')))
                    ->ignore($id),
            ],
            'required' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
