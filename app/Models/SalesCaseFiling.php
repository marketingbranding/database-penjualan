<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sales_case_id', 'bi_checking_id', 'bank_id', 'bank_unit_id', 'created_by', 'filing_id',
    'started_at', 'received_by_bank_at', 'requested_amount', 'requested_tenor', 'status',
    'completed_at', 'delay_category', 'waiting_for', 'follow_up_notes', 'deadline_at',
])]
class SalesCaseFiling extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function biChecking(): BelongsTo
    {
        return $this->belongsTo(BiChecking::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankUnit(): BelongsTo
    {
        return $this->belongsTo(BankUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankProcesses(): HasMany
    {
        return $this->hasMany(BankProcess::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'received_by_bank_at' => 'date',
            'requested_amount' => 'decimal:2',
            'completed_at' => 'date',
            'deadline_at' => 'date',
        ];
    }
}
