<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'source_system', 'source_application_id', 'source_transaction_id', 'source_customer_ref',
    'branch_id', 'project_id', 'source_current_kavling', 'source_kavling_id',
    'source_sales_user_id', 'source_sales_lead_id', 'application_status', 'consumer_status',
    'current_stage', 'source_last_process', 'source_completeness_status', 'status_cash',
    'booking_date', 'akad_date', 'notes', 'source_payload_hash', 'metadata',
])]
class ConsumerMigrationApplication extends Model
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(ConsumerMigrationReconciliation::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ConsumerMigrationRecord::class);
    }

    protected function casts(): array
    {
        return [
            'status_cash' => 'boolean',
            'booking_date' => 'date',
            'akad_date' => 'date',
            'metadata' => 'array',
        ];
    }
}
