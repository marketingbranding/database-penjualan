<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCutoverDisposition extends Model
{
    public const VALID_SALES_REPAIRABLE = 'VALID_SALES_REPAIRABLE';

    public const RECOVERED_SALES_OWNER = 'RECOVERED_SALES_OWNER';

    public const NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH = 'NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH';

    public const EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED = 'EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED';

    public const OTHER_BLOCKER = 'OTHER_BLOCKER';

    public const APPROVED_HISTORICAL_CROSS_BRANCH = 'APPROVED_HISTORICAL_CROSS_BRANCH';

    protected $fillable = [
        'lead_id', 'classification', 'evidence_confidence', 'cutover_at', 'current_month_boundary',
        'old_sales_pic_id', 'new_sales_pic_id', 'old_team_id', 'new_team_id', 'evidence', 'reason', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'cutover_at' => 'datetime',
            'current_month_boundary' => 'datetime',
            'evidence' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
