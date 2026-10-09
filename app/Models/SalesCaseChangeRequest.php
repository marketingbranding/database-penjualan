<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_case_id', 'change_type', 'process_stage', 'restart_stage', 'sp3k_issued',
    'old_consumer_id', 'new_consumer_id', 'old_lot_id', 'new_lot_id', 'reason',
    'requested_by', 'status', 'completed_at',
])]
class SalesCaseChangeRequest extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function oldConsumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'old_consumer_id');
    }

    public function newConsumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'new_consumer_id');
    }

    public function oldLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'old_lot_id');
    }

    public function newLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'new_lot_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    protected function casts(): array
    {
        return ['sp3k_issued' => 'boolean', 'completed_at' => 'datetime'];
    }
}
