<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
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
            'no_hp' => ['nullable', 'string', 'max:40'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'sales_pic_id' => ['required', 'integer', 'exists:users,id'],
            'status_lead' => ['required', Rule::in(Lead::STATUSES)],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'origin_agenda_id' => ['nullable', 'integer', 'exists:sales_agendas,id'],
        ];
    }
}
