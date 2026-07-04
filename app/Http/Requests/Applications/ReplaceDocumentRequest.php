<?php

namespace App\Http\Requests\Applications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceDocumentRequest extends FormRequest
{
    /**
     * Same file constraints as the original submission — one replacement file
     * for a single rejected document.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', StoreApplicationRequest::ALLOWED_MIMES),
                'max:'.StoreApplicationRequest::MAX_FILE_KB,
            ],
        ];
    }
}
