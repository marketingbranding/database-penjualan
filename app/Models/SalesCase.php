<?php

namespace App\Models;

use App\PaymentMethod;
use Database\Factories\SalesCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'consumer_id', 'lot_id', 'promotion_id', 'payment_method', 'notes', 'created_by',
    'consumer_status', 'consumer_status_date', 'consumer_status_reason', 'consumer_status_changed_by',
    'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class SalesCase extends Model
{
    /** @use HasFactory<SalesCaseFactory> */
    use HasFactory;

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function psjbUtj(): HasOne
    {
        return $this->hasOne(PsjbUtjCase::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(SalesCaseChangeRequest::class);
    }

    public function biCheckings(): HasMany
    {
        return $this->hasMany(BiChecking::class)->latest('checking_date');
    }

    public function filings(): HasMany
    {
        return $this->hasMany(SalesCaseFiling::class)->latest('started_at');
    }

    public function bankProcesses(): HasMany
    {
        return $this->hasMany(BankProcess::class)->orderByDesc('attempt_number');
    }

    public function ppjbDeveloper(): HasOne
    {
        return $this->hasOne(PpjbDeveloperCase::class);
    }

    public function akad(): HasOne
    {
        return $this->hasOne(AkadCase::class);
    }

    public function bast(): HasOne
    {
        return $this->hasOne(BastCase::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SalesCaseStatusHistory::class)->latest();
    }

    public function salesAgendas(): HasMany
    {
        return $this->hasMany(SalesAgenda::class);
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumer_status_changed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
            'consumer_status_date' => 'date',
        ];
    }
}
