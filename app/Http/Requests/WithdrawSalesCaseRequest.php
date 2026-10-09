<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawSalesCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales-process.update') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
