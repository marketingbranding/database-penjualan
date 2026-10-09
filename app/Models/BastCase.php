<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_case_id', 'akad_case_id', 'bast_number', 'akad_date', 'building_progress',
    'ready_100_at', 'started_at', 'bast_date', 'delay_category', 'status', 'deadline_at',
    'completed_at', 'notes',
])]
class BastCase extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function akad(): BelongsTo
    {
        return $this->belongsTo(AkadCase::class, 'akad_case_id');
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class);
    }

    protected function casts(): array
    {
        return [
            'akad_date' => 'date',
            'building_progress' => 'decimal:2',
            'ready_100_at' => 'date',
            'started_at' => 'date',
            'bast_date' => 'date',
            'deadline_at' => 'date',
            'completed_at' => 'date',
        ];
    }
}
