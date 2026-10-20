<?php

namespace App\Http\Requests;

use App\Models\SalesAgenda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agenda = $this->route('salesAgenda');

        return $agenda instanceof SalesAgenda
            && $this->user()?->can('update', $agenda) === true;
    }

    public function rules(): array
    {
        $agenda = $this->route('salesAgenda');
        $isPlannableAgenda = $agenda instanceof SalesAgenda && $agenda->duration_minutes !== null;

        return [
            'sales_case_id' => ['nullable', 'integer', 'exists:sales_cases,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'agenda_date' => ['required', 'date'],
            'activity_category' => ['required', Rule::in(SalesAgenda::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'plan_detail' => [$isPlannableAgenda ? 'required' : 'nullable', 'string', 'max:5000'],
            'duration_minutes' => [$isPlannableAgenda ? 'required' : 'nullable', 'integer', 'min:1', 'max:420'],
            'planned_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
            'activity_result' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:2'],
            'evidence.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
