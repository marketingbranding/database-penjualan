<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesCaseChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('psjb-utj.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'change_type' => ['required', Rule::in(['pindah_kavling', 'ganti_nama'])],
            'process_stage' => ['required', Rule::in(['psjb_utj', 'bi_checking', 'pemberkasan', 'proses_bank', 'ppjb'])],
            'restart_stage' => ['nullable', Rule::in(['psjb_utj', 'bi_checking'])],
            'sp3k_issued' => ['required', 'boolean'],
            'new_lot_id' => ['required_if:change_type,pindah_kavling', 'nullable', 'integer', Rule::exists('lots', 'id')->where('status', 'available')],
            'reason' => ['required', 'string', 'max:2000'],
            'new_no_ktp' => ['required_if:change_type,ganti_nama', 'nullable', 'digits:16', Rule::unique('consumers', 'no_ktp')],
            'new_nama_konsumen' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_tanggal_lahir' => ['required_if:change_type,ganti_nama', 'nullable', 'date', 'before:today'],
            'new_pekerjaan' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_detail_pekerjaan' => ['nullable', 'string', 'max:255'],
            'new_alamat' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:2000'],
            'new_kelurahan' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_kecamatan' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_kabupaten_kota' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_no_hp' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:30'],
            'new_nama_kontak_darurat' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:255'],
            'new_no_hp_kontak_darurat' => ['required_if:change_type,ganti_nama', 'nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['new_no_ktp' => preg_replace('/\D/', '', (string) $this->input('new_no_ktp'))]);
    }
}
