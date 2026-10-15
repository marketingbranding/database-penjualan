<?php

namespace App\Models;

use Database\Factories\SalesAgendaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id', 'created_by', 'sales_case_id', 'lead_id', 'agenda_date', 'activity_category',
    'owner_id', 'team_id', 'title', 'location', 'activity_result', 'status', 'completed_at',
])]
class SalesAgenda extends Model
{
    public const CATEGORIES = [
        'Canvassing',
        'Follow-up',
        'Telepon/WhatsApp',
        'Tatap Muka Konsumen',
        'Cek Lokasi',
        'TikTok Live',
        'Pembuatan Konten',
        'Event/Pameran',
        'Administrasi',
        'Rapat',
        'Lainnya',
    ];

    public const STATUSES = ['planned', 'done', 'cancelled'];

    /** @use HasFactory<SalesAgendaFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function salesCase(): BelongsTo
    {
        return $this->belongsTo(SalesCase::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(SalesAgendaEvidence::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(SalesAgendaAssignmentHistory::class)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    protected function casts(): array
    {
        return [
            'agenda_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
