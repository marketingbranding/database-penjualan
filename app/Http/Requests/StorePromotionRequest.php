<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPromotionTargets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    use ValidatesPromotionTargets;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('promotions.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    private function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.type' => ['required', Rule::in(['branch', 'project', 'block'])],
            'targets.*.target_id' => ['required', 'integer'],
        ];
    }
}
