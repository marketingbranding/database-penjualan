<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['branch_id', 'reports_to_id', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_id');
    }

    public function salesAgendas(): HasMany
    {
        return $this->hasMany(SalesAgenda::class, 'owner_id');
    }

    public function createdSalesAgendas(): HasMany
    {
        return $this->hasMany(SalesAgenda::class, 'created_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'sales_pic_id');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function canAccessAllBranches(): bool
    {
        return $this->hasGlobalRole('super_admin') || $this->hasGlobalRole('pusat');
    }

    public function globalRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'global_role_user')
            ->with('permissions')
            ->withTimestamps();
    }

    public function hasGlobalRole(string $role): bool
    {
        return $this->globalRoles->contains('name', $role);
    }

    public function hasGlobalPermission(string $permission): bool
    {
        return $this->globalRoles->contains(
            fn (Role $role): bool => $role->permissions->contains('name', $permission),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
