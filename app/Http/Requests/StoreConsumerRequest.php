<?php

namespace App\Http\Requests;

use App\LotStatus;
use App\Models\SalesCase;
use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsumerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesCase::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => $this->user()->canAccessAllBranches()
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)]
                : ['prohibited'],
            'project_id' => [
                'required', 'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $this->resolvedBranchId())
                    ->where('is_active', true)),
            ],
            'lot_id' => [
                'required', 'integer',
                Rule::exists('lots', 'id')->where(fn ($query) => $query
                    ->where('project_id', $this->integer('project_id'))
                    ->where('status', LotStatus::Available->value)),
            ],
            'no_ktp' => ['required', 'digits:16'],
            'nama_konsumen' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'detail_pekerjaan' => ['nullable', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:2000'],
            'kelurahan' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'kabupaten_kota' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:30'],
            'nama_kontak_darurat' => ['required', 'string', 'max:255'],
            'no_hp_kontak_darurat' => ['required', 'string', 'max:30'],
            'metode_pembayaran' => ['required', Rule::enum(PaymentMethod::class)],
            'promotion_id' => ['nullable', 'integer', Rule::exists('promotions', 'id')],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'no_ktp' => preg_replace('/\D/', '', (string) $this->input('no_ktp')),
            'detail_pekerjaan' => strcasecmp((string) $this->input('detail_pekerjaan'), 'Kosong') === 0
                ? null
                : $this->input('detail_pekerjaan'),
        ]);
    }

    private function resolvedBranchId(): ?int
    {
        return $this->user()->canAccessAllBranches()
            ? ($this->integer('branch_id') ?: null)
            : $this->user()->branch_id;
    }
}
