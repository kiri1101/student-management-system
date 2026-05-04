<?php

namespace App\Http\Requests\Admin\References;

use App\Models\ProgramOffering;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LevelCredentialRequirementStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'program_offering_id' => [
                'required', 'integer',
                Rule::exists('program_offerings', 'id')->whereNull('deleted_at'),
            ],
            'level' => [
                'required', 'integer', 'min:1', 'max:10',
                $this->levelWithinOfferingRange(),
            ],
            'document_type_id' => [
                'required', 'integer',
                Rule::exists('document_types', 'id')->whereNull('deleted_at'),
                Rule::unique('level_credential_requirements', 'document_type_id')
                    ->where(fn ($q) => $q
                        ->where('program_offering_id', $this->input('program_offering_id'))
                        ->where('level', $this->input('level'))),
            ],
            'required' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Closure rule: ensure `level` falls within the chosen offering's
     * [min_level, max_level]. Bails silently when `program_offering_id`
     * is missing or invalid so the upstream `exists` rule surfaces its
     * own error first.
     */
    protected function levelWithinOfferingRange(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $offeringId = $this->input('program_offering_id');

            if (! is_numeric($offeringId)) {
                return;
            }

            $offering = ProgramOffering::find($offeringId);

            if ($offering === null) {
                return;
            }

            if ($value < $offering->min_level || $value > $offering->max_level) {
                $fail(__('The :attribute must be between :min and :max for the selected program offering.', [
                    'attribute' => $attribute,
                    'min' => $offering->min_level,
                    'max' => $offering->max_level,
                ]));
            }
        };
    }
}
