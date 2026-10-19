<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'platform.roles.view' => ['View platform roles', 'platform'],
            'organizations.view' => ['View organization workspace', 'organization'],
            'organization.members.manage' => ['Manage organization members', 'organization'],
            'checklists.manage' => ['Manage checklist definitions', 'organization'],
            'inspections.manage' => ['Manage organization inspections', 'organization'],
            'inspections.assigned.view' => ['View assigned inspections', 'organization'],
            'reviews.manage' => ['Review organization inspections', 'organization'],
        ];

        $permissionIds = [];

        foreach ($permissions as $slug => [$name, $scope]) {
            $permissionIds[$slug] = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'scope' => $scope],
            )->getKey();
        }

        $rolePermissions = [
            'super_admin' => ['platform.roles.view'],
            'organization_admin' => [
                'organizations.view', 'organization.members.manage', 'checklists.manage',
                'inspections.manage', 'inspections.assigned.view', 'reviews.manage',
            ],
            'surveyor' => ['organizations.view', 'inspections.assigned.view'],
            'reviewer' => ['organizations.view', 'inspections.assigned.view', 'reviews.manage'],
        ];

        foreach ($rolePermissions as $slug => $rolePermissionSlugs) {
            $role = Role::query()->where('slug', $slug)->firstOrFail();
            $role->permissions()->syncWithoutDetaching(array_map(
                fn (string $permissionSlug): int => $permissionIds[$permissionSlug],
                $rolePermissionSlugs,
            ));
        }
    }
}
