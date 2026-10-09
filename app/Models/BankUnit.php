<?php

namespace App\Models;

use Database\Factories\BankUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bank_id', 'name', 'is_active'])]
class BankUnit extends Model
{
    /** @use HasFactory<BankUnitFactory> */
    use HasFactory;

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
