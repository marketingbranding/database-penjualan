<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['promo_id', 'name', 'is_active', 'is_global', 'start_date', 'end_date', 'description'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function salesCases(): HasMany
    {
        return $this->hasMany(SalesCase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn (Builder $query): Builder => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $today = today()->toDateString();

        return match ($status) {
            'active' => $query->active(),
            'inactive' => $query->where('is_active', false),
            'upcoming' => $query->where('is_active', true)->whereDate('start_date', '>', $today),
            'ended' => $query->where('is_active', true)->whereDate('end_date', '<', $today),
            default => $query,
        };
    }

    public function scopeMatching(Builder $query, int $branchId, int $projectId, ?int $blockId): Builder
    {
        return $query->where(function (Builder $query) use ($branchId, $projectId, $blockId): void {
            $query->where('is_global', true)->orWhereHas('targets', function ($targets) use ($branchId, $projectId, $blockId): void {
                $targets->where(function ($target) use ($branchId, $projectId, $blockId): void {
                    $target->where('branch_id', $branchId)->orWhere('project_id', $projectId);

                    if ($blockId !== null) {
                        $target->orWhere('project_block_id', $blockId);
                    }
                });
            });
        });
    }

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = today();

        return ($this->start_date === null || $this->start_date->lte($today))
            && ($this->end_date === null || $this->end_date->gte($today));
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->start_date?->isAfter(today())) {
            return 'upcoming';
        }

        if ($this->end_date?->isBefore(today())) {
            return 'ended';
        }

        return 'active';
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            'inactive' => 'Nonaktif',
            'upcoming' => 'Akan datang',
            'ended' => 'Berakhir',
            default => 'Aktif',
        };
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
