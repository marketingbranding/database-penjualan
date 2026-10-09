<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_case_id', 'from_status', 'to_status', 'reason', 'process_stage', 'user_id'])]
class SalesCaseStatusHistory extends Model
{
    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
