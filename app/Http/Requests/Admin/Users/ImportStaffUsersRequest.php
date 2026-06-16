<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportStaffUsersRequest extends FormRequest
{
    /**
     * The route is already gated by `role:admin`, so any request that reaches
     * this point is authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV file to import.',
            'file.file' => 'The upload must be a valid file.',
            'file.mimes' => 'The import file must be a CSV (.csv or .txt) file.',
            'file.max' => 'The import file may not be larger than 512 KB.',
        ];
    }
}
