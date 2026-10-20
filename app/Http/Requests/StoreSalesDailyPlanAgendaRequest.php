<?php

namespace App\Http\Requests;

use App\Models\SalesAgenda;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesDailyPlanAgendaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $plan = $this->route('salesDailyPlan');

        return $plan !== null && $this->user()?->can('update', $plan) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_category' => ['required', Rule::in(SalesAgenda::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'plan_detail' => ['required', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:420'],
            'planned_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'sales_case_id' => ['nullable', 'integer', 'exists:sales_cases,id'],
        ];
    }
}
