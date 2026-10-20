<?php

namespace App\Http\Requests;

use App\Models\SalesLeadTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesLeadTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('salesLeadTarget');

        return $target instanceof SalesLeadTarget
            && $this->user()?->can('update', $target) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lead_target' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
