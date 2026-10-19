<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_platform_roles')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->platformRoles()->where('scope', 'platform')->where('slug', 'super_admin')->exists();
    }

    public function hasOrganizationPermission(int $organizationId, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->memberships()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
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
