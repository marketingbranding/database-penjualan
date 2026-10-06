<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function globalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'global_role_user')->withTimestamps();
    }
}
