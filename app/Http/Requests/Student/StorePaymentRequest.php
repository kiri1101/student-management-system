<?php

namespace App\Http\Requests\Student;

use App\Enums\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    private const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_FILE_KB = 8192;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'bank' => ['required', Rule::enum(Bank::class)],
            'amount_xaf' => ['required', 'integer', 'min:1'],
            'bank_reference' => ['required', 'string', 'max:120'],
            'slip' => [
                'required', 'file',
                'mimes:'.implode(',', self::ALLOWED_MIMES),
                'max:'.self::MAX_FILE_KB,
            ],
        ];
    }
}
