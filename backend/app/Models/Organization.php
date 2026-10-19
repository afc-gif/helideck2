<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'status'])]
class Organization extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            $organization->public_id ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role_id', 'status'])
            ->withTimestamps();
    }

    /** @param Builder<Organization> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $query->whereHas('memberships', function (Builder $membershipQuery) use ($user): void {
            $membershipQuery->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->whereHas('role.permissions', fn (Builder $permissionQuery) => $permissionQuery->where('slug', 'organizations.view'));
        });
    }

    /** @return HasMany<SurveyType, $this> */
    public function surveyTypes(): HasMany
    {
        return $this->hasMany(SurveyType::class);
    }

    /** @return HasMany<ChecklistDefinition, $this> */
    public function checklistDefinitions(): HasMany
    {
        return $this->hasMany(ChecklistDefinition::class);
    }

    /** @return HasMany<SurveyInstance, $this> */
    public function surveys(): HasMany
    {
        return $this->hasMany(SurveyInstance::class);
    }
}
