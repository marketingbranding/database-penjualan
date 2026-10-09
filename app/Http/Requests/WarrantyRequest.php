<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('POST') ? 'warranties.create' : 'warranties.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sales_case_id' => ['required', 'integer', Rule::exists('sales_cases', 'id')->whereNull('cancelled_at')],
            'bast_case_id' => ['nullable', 'integer', 'exists:bast_cases,id'],
            'consumer_id' => ['nullable', 'integer', 'exists:consumers,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['belum_dipilih', 'tidak_ada_komplain', 'proses', 'selesai', 'review_data'])],
            'complaint_status' => ['nullable', 'string', 'max:100'],
            'reported_at' => ['nullable', 'date'],
            'sales_to_sam_at' => ['nullable', 'date'],
            'sam_to_sat_at' => ['nullable', 'date'],
            'sat_to_sam_at' => ['nullable', 'date'],
            'sam_to_sales_at' => ['nullable', 'date'],
            'sales_to_consumer_at' => ['nullable', 'date'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'resolved_at' => ['nullable', 'date'],
        ];
    }
}
