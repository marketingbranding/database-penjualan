<?php

namespace App\Models;

use Database\Factories\PsjbUtjCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_case_id', 'psjb_id', 'psjb_date', 'coordinator_id', 'sales_id', 'unit_price',
    'process_stage', 'sp3k_issued',
    'utj_date', 'utj_amount', 'dp_all_in', 'dp_installment_amount', 'dp_installment_count',
    'land_area_m2', 'land_price_per_m2', 'land_total', 'payment_detail',
    'external_transaction_id', 'external_status', 'external_method', 'notes',
    'consumer_status', 'consumer_status_date', 'delay_category', 'filing_status',
    'filing_lead_time', 'filing_lead_time_status', 'ongoing_delay_category',
    'filing_delay_reason',
])]
class PsjbUtjCase extends Model
{
    /** @use HasFactory<PsjbUtjCaseFactory> */
    use HasFactory;

    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function isOngoing(): bool
    {
        return strtoupper((string) $this->consumer_status) === 'LANJUT';
    }

    protected function casts(): array
    {
        return [
            'psjb_date' => 'date',
            'sp3k_issued' => 'boolean',
            'utj_date' => 'date',
            'consumer_status_date' => 'date',
            'unit_price' => 'decimal:2',
            'utj_amount' => 'decimal:2',
            'dp_all_in' => 'decimal:2',
            'dp_installment_amount' => 'decimal:2',
            'land_area_m2' => 'decimal:2',
            'land_price_per_m2' => 'decimal:2',
            'land_total' => 'decimal:2',
        ];
    }
}
