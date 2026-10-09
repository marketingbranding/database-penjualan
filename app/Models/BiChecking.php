<?php

namespace App\Models;

use Database\Factories\BiCheckingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_case_id', 'bank_id', 'bank_unit_id', 'checking_date', 'slik_result', 'notes',
    'lead_time_days', 'lead_time_status', 'bank_decision',
])]
class BiChecking extends Model
{
    /** @use HasFactory<BiCheckingFactory> */
    use HasFactory;

    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankUnit(): BelongsTo
    {
        return $this->belongsTo(BankUnit::class);
    }

    protected function casts(): array
    {
        return ['checking_date' => 'date'];
    }
}
