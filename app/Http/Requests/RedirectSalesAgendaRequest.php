<?php

namespace App\Http\Requests;

use App\Models\SalesAgenda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedirectSalesAgendaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $agenda = $this->route('salesAgenda');

        return $agenda instanceof SalesAgenda && $this->user()?->can('redirect', $agenda) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'agenda_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }
}
