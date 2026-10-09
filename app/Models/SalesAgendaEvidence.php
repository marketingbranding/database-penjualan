<?php

namespace App\Models;

use Database\Factories\SalesAgendaEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_agenda_id', 'file_path', 'original_name', 'mime_type', 'file_size'])]
class SalesAgendaEvidence extends Model
{
    /** @use HasFactory<SalesAgendaEvidenceFactory> */
    use HasFactory;

    public function salesAgenda(): BelongsTo
    {
        return $this->belongsTo(SalesAgenda::class);
    }
}
