<?php

namespace App\Http\Requests;

use App\Models\SalesAgenda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesAgenda::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sales_case_id' => ['nullable', 'integer', 'exists:sales_cases,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'agenda_date' => ['required', 'date'],
            'activity_category' => ['required', Rule::in(SalesAgenda::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'plan_detail' => ['required', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:420'],
            'planned_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
            'activity_result' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:2'],
            'evidence.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
