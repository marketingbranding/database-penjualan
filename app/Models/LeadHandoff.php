<?php

namespace App\Models;

use Database\Factories\LeadHandoffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'requested_by', 'reviewed_by', 'status', 'note', 'review_note', 'reviewed_at'])]
class LeadHandoff extends Model
{
    public const STATUSES = ['submitted', 'accepted', 'returned', 'cancelled'];

    /** @use HasFactory<LeadHandoffFactory> */
    use HasFactory;

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
