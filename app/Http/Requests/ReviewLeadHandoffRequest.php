<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLeadHandoffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('leads.monitor')
            || $this->user()?->can('leads.view-team')
            || $this->user()?->can('leads.view-all');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'returned', 'cancelled'])],
            'review_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
