<?php

namespace App\Http\Requests\Admin\References;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('document_types', 'name')],
            'code' => ['required', 'string', 'max:32', Rule::unique('document_types', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
