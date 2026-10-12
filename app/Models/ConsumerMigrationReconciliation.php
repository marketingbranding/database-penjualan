<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumer_migration_application_id', 'source_system', 'branch_id', 'project_id',
    'source_transaction_id', 'source_customer_ref', 'source_current_kavling',
    'source_transaction_status', 'source_bank_status', 'source_kavling_status',
    'source_stage_status', 'suggested_decision', 'decision', 'customer_id',
    'target_kavling_id', 'reconciliation_status', 'source_payload_hash', 'resolved_by',
    'resolved_at', 'metadata',
])]
class ConsumerMigrationReconciliation extends Model
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(ConsumerMigrationApplication::class, 'consumer_migration_application_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'customer_id');
    }

    public function targetKavling(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'target_kavling_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
