<?php

namespace App\Http\Requests;

use App\Models\SalesLeadTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesLeadTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesLeadTarget::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_id' => ['required', 'integer', 'exists:users,id'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'lead_target' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
