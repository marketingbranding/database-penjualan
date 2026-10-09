<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadTimeTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lead-time.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'target_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
