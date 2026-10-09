<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesCaseFilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'filing.create' : 'filing.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'bi_checking_id' => ['nullable', 'integer', 'exists:bi_checkings,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'bank_unit_id' => ['nullable', 'integer', 'exists:bank_units,id'],
            'started_at' => ['required', 'date'],
            'received_by_bank_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'requested_amount' => ['nullable', 'numeric', 'min:0'],
            'requested_tenor' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['input', 'incomplete', 'submitted', 'revision', 'completed'])],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'delay_category' => ['nullable', 'string', 'max:100'],
            'waiting_for' => ['nullable', 'string', 'max:100'],
            'follow_up_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
