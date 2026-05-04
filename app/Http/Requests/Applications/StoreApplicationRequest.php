<?php

namespace App\Http\Requests\Applications;

use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Codes always required regardless of program/level — National Identity
     * and Birth Certificate gate every application (`plan/context.md` §6.4).
     */
    private const ALWAYS_REQUIRED_CODES = ['NID', 'BIRTH'];

    private const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_FILE_KB = 8192;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $rules = [
            'program_offering_id' => [
                'required', 'integer',
                Rule::exists('program_offerings', 'id')->whereNull('deleted_at'),
            ],
            'level' => [
                'required', 'integer', 'min:1', 'max:10',
                $this->levelWithinOfferingRange(),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'previous_institute' => ['nullable', 'string', 'max:255'],
            'documents' => ['required', 'array'],
        ];

        foreach ($this->requiredDocumentCodes() as $code) {
            $rules["documents.{$code}"] = [
                'required', 'file',
                'mimes:'.implode(',', self::ALLOWED_MIMES),
                'max:'.self::MAX_FILE_KB,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->requiredDocumentCodes() as $code) {
            $messages["documents.{$code}.required"] = __('A :code document is required for this application.', ['code' => $code]);
        }

        return $messages;
    }

    /**
     * Codes that must be present in the upload set: the always-required pair
     * plus any `level_credential_requirements` rows flagged `required` for
     * the chosen `(program_offering_id, level)`.
     *
     * @return array<int, string>
     */
    public function requiredDocumentCodes(): array
    {
        $offeringId = $this->input('program_offering_id');
        $level = $this->input('level');

        if (! is_numeric($offeringId) || ! is_numeric($level)) {
            return self::ALWAYS_REQUIRED_CODES;
        }

        $credentialCodes = LevelCredentialRequirement::query()
            ->where('program_offering_id', $offeringId)
            ->where('level', $level)
            ->where('required', true)
            ->with('documentType')
            ->get()
            ->map(fn (LevelCredentialRequirement $rule) => $rule->documentType?->code)
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique([...self::ALWAYS_REQUIRED_CODES, ...$credentialCodes]));
    }

    /**
     * @return array<string, int>
     */
    public function documentTypeIdMap(): array
    {
        return DocumentType::query()
            ->whereIn('code', $this->requiredDocumentCodes())
            ->pluck('id', 'code')
            ->all();
    }

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
