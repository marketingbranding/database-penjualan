<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadQualificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && $this->user()?->can('update', $lead) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qualification_status' => ['required', Rule::in(Lead::QUALIFICATION_STATUSES)],
            'qualification_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
