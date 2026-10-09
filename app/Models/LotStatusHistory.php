<?php

namespace App\Models;

use App\LotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'user_id', 'from_status', 'to_status', 'source', 'reason'])]
class LotStatusHistory extends Model
{
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'from_status' => LotStatus::class,
            'to_status' => LotStatus::class,
        ];
    }
}
