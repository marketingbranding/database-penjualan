<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBiCheckingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bi-checking.create') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'bank_unit_id' => ['required', 'integer', Rule::exists('bank_units', 'id')->where('is_active', true)],
            'checking_date' => ['required', 'date'],
            'slik_result' => ['required', Rule::in(['OK', 'KOL 1', 'KOL 2', 'KOL 3', 'KOL 4', 'KOL 5'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'bank_decision' => ['required', Rule::in(['Lanjut', 'Reject'])],
        ];
    }
}
