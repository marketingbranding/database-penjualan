<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BastCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'bast.create' : 'bast.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'akad_case_id' => ['nullable', 'integer', 'exists:akad_cases,id'],
            'akad_date' => ['nullable', 'date'],
            'building_progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ready_100_at' => ['nullable', 'date'],
            'started_at' => ['required', 'date'],
            'bast_date' => ['nullable', 'date', 'after_or_equal:started_at'],
            'delay_category' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['pending', 'scheduled', 'completed', 'delayed', 'cancelled'])],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
