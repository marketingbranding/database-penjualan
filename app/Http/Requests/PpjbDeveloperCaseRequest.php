<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PpjbDeveloperCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'ppjb.create' : 'ppjb.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'bank_process_id' => ['nullable', 'integer', 'exists:bank_processes,id'],
            'sales_case_filing_id' => ['nullable', 'integer', 'exists:sales_case_filings,id'],
            'sp3k_date' => ['nullable', 'date'],
            'started_at' => ['required', 'date'],
            'signed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'status' => ['required', Rule::in(['draft', 'review', 'ready_to_sign', 'signed', 'cancelled'])],
            'delay_category' => ['nullable', 'string', 'max:100'],
            'waiting_for' => ['nullable', 'string', 'max:100'],
            'follow_up_notes' => ['nullable', 'string', 'max:5000'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
