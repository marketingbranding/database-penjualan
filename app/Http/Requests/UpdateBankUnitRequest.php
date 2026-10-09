<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('banks.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
