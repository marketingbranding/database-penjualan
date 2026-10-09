<?php

namespace App\Models;

use App\LotStatus;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'project_block_id', 'number', 'code', 'status'])]
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory;

    public string $statusChangeSource = 'manual';

    public ?string $statusChangeReason = null;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(ProjectBlock::class, 'project_block_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LotStatusHistory::class)->latest();
    }

    protected function casts(): array
    {
        return ['status' => LotStatus::class];
    }
}
