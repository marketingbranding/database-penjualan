<?php

namespace App\Models;

use Database\Factories\SalesDailyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sales_id', 'team_id', 'plan_date', 'status', 'submitted_at', 'submitted_by', 'last_modified_by'])]
class SalesDailyPlan extends Model
{
    public const STATUSES = ['draft', 'submitted'];

    public const REQUIRED_WORK_MINUTES = 420;

    /** @use HasFactory<SalesDailyPlanFactory> */
    use HasFactory;

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(SalesAgenda::class, 'daily_plan_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isWeekday(): bool
    {
        return $this->plan_date->isWeekday();
    }

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }
}
