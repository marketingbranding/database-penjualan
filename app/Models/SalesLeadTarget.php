<?php

namespace App\Models;

use Database\Factories\SalesLeadTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_id', 'period_year', 'period_month', 'lead_target', 'created_by', 'updated_by'])]
class SalesLeadTarget extends Model
{
    /** @use HasFactory<SalesLeadTargetFactory> */
    use HasFactory;

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'lead_target' => 'integer',
        ];
    }
}
