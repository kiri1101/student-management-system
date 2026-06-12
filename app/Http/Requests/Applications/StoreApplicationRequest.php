<?php

namespace App\Http\Requests\Applications;

use App\Models\Application;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Rules\LevelWithinOfferingRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
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
                new LevelWithinOfferingRange,
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
     * One open application per applicant: a new submission is refused while a
     * Draft/Submitted/UnderReview/DocumentsRequested one exists. Re-applying
     * after a decision (rejected, withdrawn — or admitted, e.g. for a higher
     * level) stays possible. The controller re-checks this inside its
     * transaction under a per-user lock to close the concurrent-submit race
     * (AUDIT.md AUD-005).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->userHasOpenApplication()) {
                    $validator->errors()->add(
                        'program_offering_id',
                        __('You already have an application in progress. Wait for a decision before submitting another.'),
                    );
                }
            },
        ];
    }

    public function userHasOpenApplication(): bool
    {
        return Application::query()
            ->where('user_id', $this->user()->id)
            ->whereIn('status', Application::OPEN_STATUSES)
            ->exists();
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
     * (National Identity + Birth Certificate, `plan/context.md` §6.4) plus
     * any `level_credential_requirements` rows flagged `required` for the
     * chosen `(program_offering_id, level)`.
     *
     * @return array<int, string>
     */
    public function requiredDocumentCodes(): array
    {
        $offeringId = $this->input('program_offering_id');
        $level = $this->input('level');

        if (! is_numeric($offeringId) || ! is_numeric($level)) {
            return DocumentType::PROTECTED_CODES;
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

        return array_values(array_unique([...DocumentType::PROTECTED_CODES, ...$credentialCodes]));
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
}
