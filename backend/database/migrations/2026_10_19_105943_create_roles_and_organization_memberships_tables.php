<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->timestampsTz();
            });
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('scope');
            $table->boolean('is_system')->default(true);
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('scope');
            $table->timestampsTz();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('user_platform_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['user_id', 'role_id']);
        });

        $now = now();
        $systemRoles = [
            ['slug' => 'super_admin', 'name' => 'Super Administrator', 'scope' => 'platform'],
            ['slug' => 'organization_admin', 'name' => 'Organization Administrator', 'scope' => 'organization'],
            ['slug' => 'surveyor', 'name' => 'Surveyor', 'scope' => 'organization'],
            ['slug' => 'reviewer', 'name' => 'Reviewer/Approver', 'scope' => 'organization'],
        ];

        foreach ($systemRoles as $role) {
            DB::table('roles')->insert($role + ['is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        $legacyColumns = ['id'];
        $hasLegacyOrganization = Schema::hasColumn('users', 'organization_id');
        $hasLegacyRole = Schema::hasColumn('users', 'role');
        if ($hasLegacyOrganization) {
            $legacyColumns[] = 'organization_id';
        }
        if ($hasLegacyRole) {
            $legacyColumns[] = 'role';
        }

        if (count($legacyColumns) > 1) {
            DB::table('users')->select($legacyColumns)->orderBy('id')->chunk(250, function ($users) use ($hasLegacyOrganization, $hasLegacyRole, $roleIds, $superAdminRoleId, $now): void {
                foreach ($users as $user) {
                    $legacyRole = Str::of((string) ($hasLegacyRole ? $user->role : 'surveyor'))->lower()->replace([' ', '-', '/'], '_')->toString();
                    $roleSlug = match ($legacyRole) {
                        'super_admin', 'super_administrator', 'superadministrator' => 'super_admin',
                        'organization_admin', 'org_admin' => 'organization_admin',
                        'reviewer', 'approver', 'reviewer_approver' => 'reviewer',
                        default => 'surveyor',
                    };

                    if ($roleSlug === 'super_admin') {
                        DB::table('user_platform_roles')->insertOrIgnore([
                            'user_id' => $user->id,
                            'role_id' => $superAdminRoleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        continue;
                    }

                    if ($hasLegacyOrganization && $user->organization_id !== null) {
                        DB::table('organization_memberships')->insertOrIgnore([
                            'organization_id' => $user->organization_id,
                            'user_id' => $user->id,
                            'role_id' => $roleIds[$roleSlug],
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Organization memberships can contain multiple organizations and cannot be losslessly converted to the legacy users.organization_id column. Restore this schema with a forward migration.'
        );
    }
};
