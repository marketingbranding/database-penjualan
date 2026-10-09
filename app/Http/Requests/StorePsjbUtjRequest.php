<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePsjbUtjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('psjb-utj.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_case_id' => [
                'required', 'integer',
                Rule::exists('sales_cases', 'id')->whereNull('cancelled_at'),
            ],
            'psjb_date' => ['required', 'date'],
            'process_stage' => ['required', Rule::in(['psjb_utj', 'bi_checking', 'pemberkasan', 'proses_bank', 'ppjb', 'akad', 'bast', 'akad_bast'])],
            'sp3k_issued' => ['required', 'boolean'],
            'coordinator_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'utj_date' => ['nullable', 'date'],
            'utj_amount' => ['required', 'numeric', 'min:0'],
            'dp_all_in' => ['required', 'numeric', 'min:0'],
            'dp_installment_count' => ['nullable', 'integer', 'min:1', 'max:120'],
            'land_area_m2' => ['required', 'numeric', 'min:0'],
            'land_price_per_m2' => ['required', 'numeric', 'min:0'],
            'payment_detail' => ['nullable', 'string', 'max:2000'],
            'external_transaction_id' => ['nullable', 'string', 'max:100'],
            'external_status' => ['nullable', 'string', 'max:100'],
            'external_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consumer_status' => ['nullable', Rule::in(['LANJUT', 'MUNDUR'])],
            'consumer_status_date' => ['nullable', 'date'],
            'delay_category' => ['nullable', 'string', 'max:100'],
            'filing_status' => ['nullable', 'string', 'max:50'],
            'ongoing_delay_category' => ['nullable', 'string', 'max:100'],
            'filing_delay_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
