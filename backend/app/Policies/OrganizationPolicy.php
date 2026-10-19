<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->memberships()
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', 'organizations.view'))
            ->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization->getKey(), 'organizations.view');
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization->getKey(), 'organization.members.manage');
    }

    public function manageChecklists(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization->getKey(), 'checklists.manage');
    }

    public function manageInspections(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization->getKey(), 'inspections.manage');
    }

    public function viewSurveys(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin()
            || $user->hasOrganizationPermission($organization->getKey(), 'inspections.manage')
            || $user->hasOrganizationPermission($organization->getKey(), 'inspections.assigned.view');
    }

    public function reviewInspections(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization->getKey(), 'reviews.manage');
    }
}
