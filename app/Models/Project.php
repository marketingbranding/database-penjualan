<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['branch_id', 'code', 'name', 'is_active'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ProjectBlock::class)->orderBy('sort_order')->orderBy('code');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function salesCases(): HasManyThrough
    {
        return $this->hasManyThrough(
            SalesCase::class,
            Lot::class,
            'project_id',
            'lot_id',
        )->whereNull('sales_cases.cancelled_at');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
