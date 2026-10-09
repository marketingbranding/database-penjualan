<?php

namespace App\Models;

use Database\Factories\ConsumerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'no_ktp', 'nama_konsumen', 'tanggal_lahir', 'pekerjaan', 'detail_pekerjaan',
    'alamat', 'kelurahan', 'kecamatan', 'kabupaten_kota', 'no_hp',
    'nama_kontak_darurat', 'no_hp_kontak_darurat',
])]
class Consumer extends Model
{
    /** @use HasFactory<ConsumerFactory> */
    use HasFactory;

    public function salesCases(): HasMany
    {
        return $this->hasMany(SalesCase::class);
    }

    public function getAgeAttribute(): int
    {
        return $this->tanggal_lahir->age;
    }

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }
}
