<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Support\BusinessTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadFollowUpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('next_follow_up_at')) {
            $this->merge(['next_follow_up_at' => BusinessTime::fromInput($this->string('next_follow_up_at')->toString())]);
        }
    }

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
            'next_follow_up_at' => ['nullable', 'date'],
            'next_follow_up_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
