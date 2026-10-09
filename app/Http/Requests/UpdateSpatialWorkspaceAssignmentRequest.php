<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpatialWorkspaceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.assign-roles') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('is_active', true)],
            'reports_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
