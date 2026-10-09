<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && $this->user()?->can('update', $lead) === true;
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in(Lead::ACTIVITY_TYPES)],
            'description' => ['required', 'string', 'max:5000'],
            'status_lead' => ['nullable', Rule::in(Lead::STATUSES)],
            'happened_at' => ['required', 'date'],
        ];
    }
}
