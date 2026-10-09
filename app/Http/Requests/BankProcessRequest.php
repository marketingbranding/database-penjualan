<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'bank-process.create' : 'bank-process.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'sales_case_filing_id' => ['nullable', 'integer', 'exists:sales_case_filings,id'],
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'bank_unit_id' => ['required', 'integer', Rule::exists('bank_units', 'id')->where('is_active', true)],
            'submitted_at' => ['required', 'date'],
            'sp3k_number' => ['nullable', 'string', 'max:100'],
            'sp3k_date' => ['nullable', 'date', 'after_or_equal:submitted_at'],
            'result' => ['nullable', Rule::in(['APPROVE', 'REJECT', 'REVISI'])],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'approved_tenor' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['proses', 'approved', 'reject', 'revisi'])],
            'revision_category' => ['nullable', 'string', 'max:100'],
            'revision_detail' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
