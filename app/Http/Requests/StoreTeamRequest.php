<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teams.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('is_active', true)],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'coordinator_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBranchRelationships($validator);
        });
    }

    private function validateBranchRelationships(Validator $validator): void
    {
        $branchId = $this->integer('branch_id') ?: null;
        $projectId = $this->integer('project_id') ?: null;

        if ($branchId !== null && $projectId !== null
            && ! Project::query()->whereKey($projectId)->where('branch_id', $branchId)->exists()) {
            $validator->errors()->add('project_id', 'Proyek harus berada di cabang tim.');
        }

        $supervisorId = $this->integer('supervisor_id') ?: null;
        if ($supervisorId !== null) {
            $supervisor = User::query()->with('globalRoles')->find($supervisorId);

            if ($supervisor === null || $supervisor->branch_id !== $branchId || ! $supervisor->hasGlobalRole('spv')) {
                $validator->errors()->add('supervisor_id', 'SPV harus merupakan SPV aktif dari cabang tim.');
            }
        }

        if ($this->boolean('is_active') && $this->integer('coordinator_id') > 0 && $projectId === null) {
            $validator->errors()->add('project_id', 'Tim koordinator yang aktif wajib memiliki proyek.');
        }

        if ($this->integer('coordinator_id') > 0) {
            $validator->errors()->add('coordinator_id', 'Simpan tim terlebih dahulu, lalu tetapkan role Koordinator Sales melalui akses pengguna.');
        }

        if ($projectId !== null && $this->boolean('is_active') && $this->integer('coordinator_id') > 0
            && DB::table('teams')->where('project_id', $projectId)->where('is_active', true)->whereNotNull('coordinator_id')->count() >= 2) {
            $validator->errors()->add('project_id', 'Satu proyek maksimal memiliki 2 tim koordinator aktif.');
        }
    }
}
