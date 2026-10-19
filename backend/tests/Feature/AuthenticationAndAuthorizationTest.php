<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_remains_public_and_protected_endpoints_reject_guests(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_user_can_login_and_receive_only_safe_profile_fields(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'member@example.test', 'password' => 'correct horse battery']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => ' MEMBER@example.test ', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertJsonPath('data.email', 'member@example.test')
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->withHeader('Origin', 'http://localhost:5173')->getJson('/api/me')->assertOk();
    }

    public function test_invalid_credentials_are_rejected_without_disclosing_account_state(): void
    {
        User::factory()->create(['email' => 'member@example.test', 'password' => 'correct horse battery']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => 'member@example.test', 'password' => 'wrong password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }

    public function test_login_attempts_are_rate_limited_per_email_and_address(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/login', ['email' => 'limited@example.test', 'password' => 'incorrect'])
                ->assertUnprocessable();
        }

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => 'limited@example.test', 'password' => 'incorrect'])
            ->assertTooManyRequests();
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Origin', 'http://localhost:5173')->actingAs($user, 'web');

        $this->postJson('/api/logout')->assertOk()->assertJsonPath('message', 'Signed out.');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_ignores_privileged_attributes_in_the_request(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'member@example.test', 'password' => 'correct horse battery']);
        $organization = Organization::factory()->create();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'correct horse battery',
                'role' => 'super_admin',
                'organization_id' => $organization->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonPath('data.memberships', []);

        $this->assertDatabaseMissing('user_platform_roles', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('organization_memberships', ['user_id' => $user->getKey()]);
    }

    public function test_initial_administrator_command_refuses_to_create_another_platform_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $existingAdmin = User::factory()->create();
        $existingAdmin->platformRoles()->attach(Role::query()->where('slug', 'super_admin')->firstOrFail());

        $this->artisan('artsci:admin:create', [
            'name' => 'Second Administrator',
            'email' => 'second-admin@example.test',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.test']);
    }

    public function test_members_only_see_their_own_organizations(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $ownOrganization = $this->addMembership($user, 'surveyor');
        $otherOrganization = Organization::factory()->create();

        $this->withHeader('Origin', 'http://localhost:5173')->actingAs($user, 'web');

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $ownOrganization->public_id);
        $this->getJson('/api/organizations/'.$otherOrganization->public_id)->assertNotFound();
    }

    public function test_user_can_have_distinct_roles_in_multiple_organizations(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $firstOrganization = $this->addMembership($user, 'surveyor');
        $secondOrganization = $this->addMembership($user, 'reviewer');

        $this->actingAs($user, 'web')
            ->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertNotSame($firstOrganization->getKey(), $secondOrganization->getKey());
        $this->assertSame(2, $user->memberships()->count());
    }

    public function test_platform_role_listing_is_restricted_to_super_administrators(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $surveyor = User::factory()->create();

        $this->actingAs($surveyor, 'web')->getJson('/api/platform/roles')->assertForbidden();

    }

    public function test_super_administrator_can_view_platform_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $superAdministrator = User::factory()->create();
        $superAdministrator->platformRoles()->attach(Role::query()->where('slug', 'super_admin')->firstOrFail());

        $this->actingAs($superAdministrator, 'web')
            ->getJson('/api/platform/roles')
            ->assertOk()
            ->assertJsonPath('data.0.scope', 'organization');
    }

    public function test_only_organization_administrators_can_read_member_directory(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $organization = Organization::factory()->create();
        $surveyor = User::factory()->create();
        OrganizationMembership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $surveyor->getKey(),
            'role_id' => Role::query()->where('slug', 'surveyor')->firstOrFail()->getKey(),
            'status' => 'active',
        ]);

        $this->actingAs($surveyor, 'web')
            ->getJson('/api/organizations/'.$organization->public_id.'/members')
            ->assertForbidden();
    }

    #[DataProvider('organizationPermissionMatrix')]
    public function test_organization_permissions_follow_membership_role(string $roleSlug, string $ability, bool $expected): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $organization = $this->addMembership($user, $roleSlug);
        $policy = app(OrganizationPolicy::class);

        $this->assertSame($expected, $policy->{$ability}($user, $organization));
    }

    public static function organizationPermissionMatrix(): array
    {
        return [
            'organization admin manages members' => ['organization_admin', 'manageMembers', true],
            'surveyor cannot manage members' => ['surveyor', 'manageMembers', false],
            'reviewer cannot manage members' => ['reviewer', 'manageMembers', false],
            'organization admin manages checklists' => ['organization_admin', 'manageChecklists', true],
            'surveyor cannot manage checklists' => ['surveyor', 'manageChecklists', false],
            'reviewer cannot manage checklists' => ['reviewer', 'manageChecklists', false],
            'organization admin manages inspections' => ['organization_admin', 'manageInspections', true],
            'surveyor cannot manage inspections' => ['surveyor', 'manageInspections', false],
            'reviewer cannot manage inspections' => ['reviewer', 'manageInspections', false],
            'organization admin can review inspections' => ['organization_admin', 'reviewInspections', true],
            'surveyor cannot review inspections' => ['surveyor', 'reviewInspections', false],
            'reviewer can review inspections' => ['reviewer', 'reviewInspections', true],
        ];
    }

    private function addMembership(User $user, string $roleSlug): Organization
    {
        $organization = Organization::factory()->create();
        OrganizationMembership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->getKey(),
            'status' => 'active',
        ]);

        return $organization;
    }
}
