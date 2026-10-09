<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_case_id', 'sales_case_filing_id', 'bank_id', 'bank_unit_id', 'sp3k_number',
    'attempt_number', 'submitted_at', 'sp3k_date', 'result', 'approved_amount', 'approved_tenor',
    'status', 'revision_category', 'revision_detail', 'notes', 'started_at', 'completed_at',
])]
class BankProcess extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(SalesCaseFiling::class, 'sales_case_filing_id');
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
        return [
            'submitted_at' => 'date',
            'sp3k_date' => 'date',
            'approved_amount' => 'decimal:2',
            'started_at' => 'date',
            'completed_at' => 'date',
        ];
    }
}
