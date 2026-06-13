<?php

namespace App\Http\Requests\Admin\References;

use App\Models\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var DocumentType|null $documentType */
        $documentType = $this->route('document_type');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('document_types', 'name')->ignore($documentType?->id)],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('document_types', 'code')->ignore($documentType?->id),
                // NID/BIRTH are referenced by code-string from the application
                // form; renaming them would orphan that wiring (AUD-014).
                function (string $attribute, mixed $value, \Closure $fail) use ($documentType): void {
                    if ($documentType?->isProtected() && $value !== $documentType->code) {
                        $fail(__('The :code code is required on every application and cannot be renamed.', [
                            'code' => $documentType->code,
                        ]));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
