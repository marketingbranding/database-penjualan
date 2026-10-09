<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_case_id', 'bank_process_id', 'sales_case_filing_id', 'ppjb_number', 'sp3k_date',
    'started_at', 'signed_at', 'status', 'delay_category', 'waiting_for', 'follow_up_notes',
    'deadline_at', 'completed_at',
])]
class PpjbDeveloperCase extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function bankProcess(): BelongsTo
    {
        return $this->belongsTo(BankProcess::class);
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(SalesCaseFiling::class, 'sales_case_filing_id');
    }

    public function akad(): HasOne
    {
        return $this->hasOne(AkadCase::class);
    }

    protected function casts(): array
    {
        return [
            'sp3k_date' => 'date',
            'started_at' => 'date',
            'signed_at' => 'date',
            'deadline_at' => 'date',
            'completed_at' => 'date',
        ];
    }
}
