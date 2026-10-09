<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'reports_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'team_ids' => ['array'],
            'team_ids.*' => ['integer', Rule::exists('teams', 'id')->where('is_active', true)],
            'default_team_id' => ['nullable', 'integer', Rule::in($this->input('team_ids', []))],
            'global_role_ids' => ['array'],
            'global_role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->whereNull('team_id'),
            ],
            'team_role_ids' => ['array'],
            'team_role_ids.*' => ['array'],
            'team_role_ids.*.*' => ['integer', Rule::exists('roles', 'id')->whereNotNull('team_id')],
        ];
    }
}
