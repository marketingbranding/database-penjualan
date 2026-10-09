<?php

namespace App\Models;

use Database\Factories\PromotionTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['promotion_id', 'branch_id', 'project_id', 'project_block_id'])]
class PromotionTarget extends Model
{
    /** @use HasFactory<PromotionTargetFactory> */
    use HasFactory;

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectBlock(): BelongsTo
    {
        return $this->belongsTo(ProjectBlock::class);
    }

    public function targetLabel(): string
    {
        return match (true) {
            $this->branch !== null => 'Cabang '.$this->branch->name,
            $this->project !== null => 'Proyek '.$this->project->name,
            $this->projectBlock !== null => 'Blok '.$this->projectBlock->code,
            default => '-',
        };
    }
}
