<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_case_id', 'ppjb_developer_case_id', 'akad_number', 'ppjb_signed_at', 'started_at',
    'akad_date', 'quality', 'down_payment_status', 'consumer_issue', 'bank_issue', 'issue_detail',
    'building_progress', 'utility_status', 'status', 'deadline_at', 'completed_at',
])]
class AkadCase extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function ppjb(): BelongsTo
    {
        return $this->belongsTo(PpjbDeveloperCase::class, 'ppjb_developer_case_id');
    }

    public function bast(): HasOne
    {
        return $this->hasOne(BastCase::class);
    }

    protected function casts(): array
    {
        return [
            'ppjb_signed_at' => 'date',
            'started_at' => 'date',
            'akad_date' => 'date',
            'building_progress' => 'decimal:2',
            'deadline_at' => 'date',
            'completed_at' => 'date',
        ];
    }
}
