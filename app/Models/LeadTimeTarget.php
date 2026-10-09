<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['process', 'target_description', 'target_days', 'is_active'])]
class LeadTimeTarget extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
