<?php

namespace App\Models;

use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    public function units(): HasMany
    {
        return $this->hasMany(BankUnit::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
