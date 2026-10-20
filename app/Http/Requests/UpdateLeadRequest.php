<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['no_hp' => trim((string) $this->input('no_hp', ''))]);
    }

    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && $this->user()?->can('update', $lead) === true;
    }

    public function rules(): array
    {
        return [
            'id_promo' => ['nullable', 'string', 'max:100'],
            'nama_promo' => ['nullable', 'string', 'max:255'],
            'tanggal_lead' => ['required', 'date'],
            'sumber_lead' => ['required', Rule::in(Lead::SOURCES)],
            'kanal_masuk' => ['required', Rule::in(Lead::CHANNELS)],
            'aktivitas_lead' => ['required', Rule::in(Lead::ACTIVITIES)],
            'nama_konsumen' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:40'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'sales_pic_id' => ['required', 'integer', 'exists:users,id'],
            'status_lead' => ['required', Rule::in(Lead::STATUSES)],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
