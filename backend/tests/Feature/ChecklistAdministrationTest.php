<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checklist_api_requires_authentication_and_checklist_permission(): void
    {
        $organization = Organization::factory()->create();
        $this->getJson($this->checklistPath($organization))->assertUnauthorized();

        $surveyor = $this->addMember($organization, 'surveyor');
        $this->actingAs($surveyor, 'web')
            ->getJson($this->checklistPath($organization))
            ->assertForbidden();
    }

    public function test_organization_admin_can_create_and_read_a_draft_checklist(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->addMember($organization, 'organization_admin');

        $response = $this->actingAs($administrator, 'web')
            ->postJson($this->checklistPath($organization), [
                'name' => 'Offshore review template',
                'description' => 'Admin-authored checklist structure.',
                'survey_type' => 'offshore-helideck',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Offshore review template')
            ->assertJsonPath('data.survey_type.slug', 'offshore-helideck')
            ->assertJsonPath('data.versions.0.version_number', 1)
            ->assertJsonPath('data.versions.0.status', 'draft')
            ->assertJsonPath('data.versions.0.sections', []);

        $checklistPublicId = $response->json('data.public_id');

        $this->getJson($this->checklistPath($organization).'/'.$checklistPublicId)
            ->assertOk()
            ->assertJsonPath('data.public_id', $checklistPublicId);
    }

    public function test_checklist_data_is_isolated_between_organizations(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $administrator = $this->addMember($organization, 'organization_admin');
        $this->actingAs($administrator, 'web');

        $checklist = $this->postJson($this->checklistPath($organization), [
            'name' => 'Private checklist',
            'survey_type' => 'onshore-heliport',
        ])->assertCreated()->json('data.public_id');

        $this->getJson($this->checklistPath($otherOrganization).'/'.$checklist)->assertNotFound();
    }

    public function test_published_versions_are_immutable_and_new_versions_clone_existing_content(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->addMember($organization, 'organization_admin');
        $this->actingAs($administrator, 'web');
        $basePath = $this->checklistPath($organization);

        $checklist = $this->postJson($basePath, [
            'name' => 'Checklist',
            'survey_type' => 'offshore-helideck',
        ])->assertCreated()->json('data.public_id');

        $this->putJson("{$basePath}/{$checklist}/versions/1", $this->validContent('Original prompt'))
            ->assertOk();
        $this->postJson("{$basePath}/{$checklist}/versions/1/publish")
            ->assertOk()
            ->assertJsonPath('data.versions.0.status', 'published');

        $this->postJson("{$basePath}/{$checklist}/versions")
            ->assertCreated()
            ->assertJsonPath('data.versions.1.version_number', 2)
            ->assertJsonPath('data.versions.1.status', 'draft')
            ->assertJsonPath('data.versions.1.sections.0.questions.0.label', 'Original prompt');

        $this->putJson("{$basePath}/{$checklist}/versions/2", $this->validContent('Revised prompt'))
            ->assertOk()
            ->assertJsonPath('data.versions.0.sections.0.questions.0.label', 'Original prompt')
            ->assertJsonPath('data.versions.1.sections.0.questions.0.label', 'Revised prompt');

        $this->putJson("{$basePath}/{$checklist}/versions/1", $this->validContent('Silently changed'))
            ->assertStatus(409);
        $this->postJson("{$basePath}/{$checklist}/versions/1/publish")->assertStatus(409);
    }

    public function test_select_questions_require_options_and_empty_versions_cannot_be_published(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->addMember($organization, 'organization_admin');
        $this->actingAs($administrator, 'web');
        $basePath = $this->checklistPath($organization);
        $checklist = $this->postJson($basePath, [
            'name' => 'Checklist',
            'survey_type' => 'onshore-heliport',
        ])->assertCreated()->json('data.public_id');

        $this->putJson("{$basePath}/{$checklist}/versions/1", [
            'sections' => [[
                'title' => 'Section',
                'questions' => [[
                    'label' => 'Admin-authored prompt',
                    'field_type' => 'single_select',
                    'is_required' => true,
                    'options' => [],
                ]],
            ]],
        ])->assertUnprocessable();

        $this->postJson("{$basePath}/{$checklist}/versions/1/publish")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Add at least one section with a question before publishing.');
    }

    public function test_only_one_draft_version_can_exist_at_a_time(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->addMember($organization, 'organization_admin');
        $this->actingAs($administrator, 'web');
        $basePath = $this->checklistPath($organization);
        $checklist = $this->postJson($basePath, [
            'name' => 'Checklist',
            'survey_type' => 'offshore-helideck',
        ])->assertCreated()->json('data.public_id');

        $this->postJson("{$basePath}/{$checklist}/versions")->assertStatus(409);
    }

    /** @return array{sections: array<int, array<string, mixed>>} */
    private function validContent(string $label): array
    {
        return [
            'sections' => [[
                'title' => 'Section one',
                'description' => null,
                'questions' => [[
                    'stable_key' => 'e544a5a5-4d7c-49c7-a3f4-2d34c131f23a',
                    'label' => $label,
                    'description' => null,
                    'instructions' => null,
                    'field_type' => 'text',
                    'is_required' => true,
                    'unit' => null,
                    'options' => [],
                ]],
            ]],
        ];
    }

    private function addMember(Organization $organization, string $roleSlug): User
    {
        $user = User::factory()->create();
        OrganizationMembership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->getKey(),
            'status' => 'active',
        ]);

        return $user;
    }

    private function checklistPath(Organization $organization): string
    {
        return '/api/organizations/'.$organization->public_id.'/checklists';
    }
}
