<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teams.update') ?? false;
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('teams', 'id'),
                Rule::notIn([$this->team->getKey()]),
            ],
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
            $branchId = $this->integer('branch_id') ?: null;
            $projectId = $this->integer('project_id') ?: null;
            $coordinatorId = $this->integer('coordinator_id') ?: null;

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

            if ($this->boolean('is_active') && $coordinatorId !== null && $projectId === null) {
                $validator->errors()->add('project_id', 'Tim koordinator yang aktif wajib memiliki proyek.');
            }

            if ($projectId !== null && $this->boolean('is_active') && $coordinatorId !== null) {
                $activeCoordinatorCount = $this->team->newQuery()
                    ->where('project_id', $projectId)
                    ->where('is_active', true)
                    ->whereNotNull('coordinator_id')
                    ->whereKeyNot($this->team)
                    ->count();

                if ($activeCoordinatorCount >= 2) {
                    $validator->errors()->add('project_id', 'Satu proyek maksimal memiliki 2 tim koordinator aktif.');
                }
            }

            if ($coordinatorId !== null) {
                $coordinator = User::query()->with('globalRoles')->find($coordinatorId);
                $role = Role::query()->where('team_id', $this->team->getKey())->where('name', 'koordinator_sales')->first();
                $hasTeamRole = $role !== null && DB::table(config('permission.table_names.model_has_roles'))
                    ->where('role_id', $role->getKey())
                    ->where('model_type', (new User)->getMorphClass())
                    ->where('model_id', $coordinatorId)
                    ->exists();

                if ($coordinator === null || $coordinator->branch_id !== $branchId) {
                    $validator->errors()->add('coordinator_id', 'Koordinator harus berasal dari cabang tim.');
                } elseif (! $coordinator->teams()->whereKey($this->team)->exists()) {
                    $validator->errors()->add('coordinator_id', 'Koordinator harus menjadi anggota tim ini.');
                } elseif (! $hasTeamRole) {
                    $validator->errors()->add('coordinator_id', 'Koordinator harus memiliki role Koordinator Sales pada tim ini.');
                }
            }
        });
    }
}
