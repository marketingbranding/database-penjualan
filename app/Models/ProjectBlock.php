<?php

namespace App\Models;

use Database\Factories\ProjectBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'code', 'sort_order', 'is_active'])]
class ProjectBlock extends Model
{
    /** @use HasFactory<ProjectBlockFactory> */
    use HasFactory;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class)->orderBy('number');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
