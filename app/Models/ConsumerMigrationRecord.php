<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumer_migration_application_id', 'source_system', 'source_transaction_id',
    'source_type', 'source_record_id', 'source_branch_code', 'payload_hash', 'payload',
    'target_type', 'target_id',
])]
class ConsumerMigrationRecord extends Model
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(ConsumerMigrationApplication::class, 'consumer_migration_application_id');
    }

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
