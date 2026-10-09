<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_case_id', 'bast_case_id', 'consumer_id', 'lot_id', 'assigned_to', 'warranty_number',
    'status', 'complaint_status', 'reported_at', 'sales_to_sam_at', 'sam_to_sat_at',
    'sat_to_sam_at', 'sam_to_sales_at', 'sales_to_consumer_at', 'detail', 'resolved_at',
])]
class Warranty extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function bast(): BelongsTo
    {
        return $this->belongsTo(BastCase::class, 'bast_case_id');
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'date',
            'sales_to_sam_at' => 'date',
            'sam_to_sat_at' => 'date',
            'sat_to_sam_at' => 'date',
            'sam_to_sales_at' => 'date',
            'sales_to_consumer_at' => 'date',
            'resolved_at' => 'date',
        ];
    }
}
