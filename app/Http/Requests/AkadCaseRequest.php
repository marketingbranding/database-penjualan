<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AkadCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'akad.create' : 'akad.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'ppjb_developer_case_id' => ['nullable', 'integer', 'exists:ppjb_developer_cases,id'],
            'ppjb_signed_at' => ['nullable', 'date'],
            'started_at' => ['required', 'date'],
            'akad_date' => ['nullable', 'date', 'after_or_equal:started_at'],
            'quality' => ['nullable', 'string', 'max:100'],
            'down_payment_status' => ['nullable', 'string', 'max:100'],
            'consumer_issue' => ['nullable', 'string', 'max:100'],
            'bank_issue' => ['nullable', 'string', 'max:100'],
            'issue_detail' => ['nullable', 'string', 'max:5000'],
            'building_progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'utility_status' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['pending', 'scheduled', 'completed', 'delayed', 'cancelled'])],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
