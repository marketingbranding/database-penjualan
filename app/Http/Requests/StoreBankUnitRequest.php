<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBankUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('banks.create') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100']];
    }
}
