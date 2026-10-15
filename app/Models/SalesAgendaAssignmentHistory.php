<?php

namespace App\Models;

use Database\Factories\SalesAgendaAssignmentHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_agenda_id', 'action', 'from_user_id', 'to_user_id', 'by_user_id', 'reason'])]
class SalesAgendaAssignmentHistory extends Model
{
    /** @use HasFactory<SalesAgendaAssignmentHistoryFactory> */
    use HasFactory;

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(SalesAgenda::class, 'sales_agenda_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }
}
