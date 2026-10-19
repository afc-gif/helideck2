<?php

namespace Tests\Feature;

use App\Models\ChecklistDefinition;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistVersion;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\SurveyInstance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_surveys_require_authentication_and_an_inspection_permission(): void
    {
        $organization = Organization::factory()->create();
        $this->getJson($this->surveysPath($organization))->assertUnauthorized();

        $member = $this->addMember($organization, 'surveyor');
        $this->app['auth']->forgetGuards();
        $this->actingAs($member, 'web')->getJson($this->surveysPath($organization))->assertOk();

        $unprivileged = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->actingAs($unprivileged, 'web')->getJson($this->surveysPath($organization))->assertNotFound();
    }

    public function test_inspector_can_create_from_published_version_and_resume_saved_answers(): void
    {
        $organization = Organization::factory()->create();
        $inspector = $this->addMember($organization, 'surveyor');
        [$definition, $version, $question] = $this->publishedTemplate($organization, $inspector, 'offshore-helideck', 'decimal');
        $this->actingAs($inspector, 'web');

        $response = $this->postJson($this->surveysPath($organization), [
            'checklist_public_id' => $definition->public_id,
            'version_number' => $version->version_number,
            'site_name' => 'Test installation',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.checklist.version_number', 1)
            ->assertJsonPath('data.sections.0.questions.0.public_id', $question->public_id);

        $surveyId = $response->json('data.public_id');
        $this->putJson($this->surveysPath($organization)."/{$surveyId}/answers", [
            'status' => 'in_progress',
            'answers' => [[
                'question_public_id' => $question->public_id,
                'value' => 12.5,
                'comment' => 'Observed value, entered by inspector.',
            ]],
        ])->assertOk()->assertJsonPath("data.answers.{$question->public_id}.value", 12.5)
            ->assertJsonPath("data.answers.{$question->public_id}.comment", 'Observed value, entered by inspector.');

        $this->getJson($this->surveysPath($organization)."/{$surveyId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath("data.answers.{$question->public_id}.value", 12.5);
    }

    public function test_instance_stays_pinned_to_its_version_after_a_later_version_is_published(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        [$definition, $versionOne, $questionOne] = $this->publishedTemplate($organization, $admin, 'onshore-heliport', 'text');
        $this->actingAs($admin, 'web');
        $surveyId = $this->postJson($this->surveysPath($organization), [
            'checklist_public_id' => $definition->public_id,
            'version_number' => 1,
            'site_name' => 'North Bank',
        ])->assertCreated()->json('data.public_id');
        $originalTemplateName = $definition->name;
        $definition->update(['name' => 'Renamed template after survey start']);

        $versionTwo = $definition->versions()->create(['version_number' => 2, 'status' => 'published', 'published_at' => now(), 'published_by' => $admin->getKey()]);
        $sectionTwo = $versionTwo->sections()->create(['title' => 'Revised section', 'position' => 1]);
        $sectionTwo->questions()->create(['stable_key' => 'question-v2', 'label' => 'Added in version two', 'field_type' => 'text', 'is_required' => false, 'position' => 1]);

        $this->getJson($this->surveysPath($organization)."/{$surveyId}")
            ->assertOk()
            ->assertJsonPath('data.checklist.version_number', 1)
            ->assertJsonPath('data.checklist.name', $originalTemplateName)
            ->assertJsonPath('data.sections.0.questions.0.public_id', $questionOne->public_id)
            ->assertJsonMissing(['label' => 'Added in version two']);
        $this->assertSame($versionOne->getKey(), SurveyInstance::query()->where('public_id', $surveyId)->value('checklist_version_id'));
    }

    public function test_only_published_versions_of_the_two_supported_survey_types_are_available(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        $this->publishedTemplate($organization, $admin, 'offshore-helideck', 'text');
        $this->publishedTemplate($organization, $admin, 'onshore-heliport', 'text');
        $this->publishedTemplate($organization, $admin, 'custom-type', 'text');
        $this->actingAs($admin, 'web')->getJson('/api/organizations/'.$organization->public_id.'/survey-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_create_a_survey_from_a_draft_or_another_organizations_template(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        $otherAdmin = $this->addMember($otherOrganization, 'organization_admin');
        [$otherDefinition] = $this->publishedTemplate($otherOrganization, $otherAdmin, 'offshore-helideck', 'text');
        $surveyType = $organization->surveyTypes()->create(['name' => 'Offshore helideck', 'slug' => 'offshore-helideck']);
        $draftDefinition = $organization->checklistDefinitions()->create(['survey_type_id' => $surveyType->getKey(), 'name' => 'Draft only']);
        $draftDefinition->versions()->create(['version_number' => 1, 'status' => 'draft']);
        $this->actingAs($admin, 'web');

        $this->postJson($this->surveysPath($organization), ['checklist_public_id' => $draftDefinition->public_id, 'version_number' => 1, 'site_name' => 'Site'])->assertNotFound();
        $this->postJson($this->surveysPath($organization), ['checklist_public_id' => $otherDefinition->public_id, 'version_number' => 1, 'site_name' => 'Site'])->assertNotFound();
    }

    public function test_surveyor_can_only_view_and_edit_surveys_assigned_to_them(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        $assignedInspector = $this->addMember($organization, 'surveyor');
        $otherInspector = $this->addMember($organization, 'surveyor');
        [$definition, $version, $question] = $this->publishedTemplate($organization, $admin, 'offshore-helideck', 'text');
        $survey = $organization->surveys()->create([
            'checklist_definition_id' => $definition->getKey(), 'checklist_version_id' => $version->getKey(),
            'checklist_name_snapshot' => $definition->name, 'survey_type_slug_snapshot' => 'offshore-helideck',
            'site_name' => 'Assigned site', 'assigned_to_user_id' => $assignedInspector->getKey(), 'created_by' => $admin->getKey(), 'status' => 'in_progress',
        ]);
        $path = $this->surveysPath($organization).'/'.$survey->public_id;

        $this->actingAs($otherInspector, 'web')->getJson($path)->assertNotFound();
        $this->putJson($path.'/answers', ['answers' => [['question_public_id' => $question->public_id, 'value' => 'No access']]])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->actingAs($assignedInspector, 'web')->getJson($path)->assertOk();
        $this->putJson($path.'/answers', ['answers' => [['question_public_id' => $question->public_id, 'value' => 'Assigned inspector']]])->assertOk();
    }

    public function test_organization_manager_cannot_access_another_organizations_survey(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        $otherAdmin = $this->addMember($otherOrganization, 'organization_admin');
        [$definition, $version] = $this->publishedTemplate($otherOrganization, $otherAdmin, 'offshore-helideck', 'text');
        $survey = $otherOrganization->surveys()->create([
            'checklist_definition_id' => $definition->getKey(), 'checklist_version_id' => $version->getKey(),
            'checklist_name_snapshot' => $definition->name, 'survey_type_slug_snapshot' => 'offshore-helideck',
            'site_name' => 'Private site', 'assigned_to_user_id' => $otherAdmin->getKey(), 'created_by' => $otherAdmin->getKey(), 'status' => 'in_progress',
        ]);

        $this->actingAs($admin, 'web')->getJson($this->surveysPath($organization).'/'.$survey->public_id)->assertNotFound();
    }

    public function test_answers_are_validated_against_pinned_question_types_and_options(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        [$definition, $version, $question] = $this->publishedTemplate($organization, $admin, 'offshore-helideck', 'single_select', ['yes', 'no']);
        $this->actingAs($admin, 'web');
        $surveyId = $this->createSurvey($organization, $definition, $version);
        $path = $this->surveysPath($organization)."/{$surveyId}/answers";

        $this->putJson($path, ['answers' => [['question_public_id' => $question->public_id, 'value' => 'unknown-option']]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => $question->public_id, 'value' => ['yes']]]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => fake()->uuid(), 'value' => 'yes']]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => $question->public_id, 'value' => 'yes']]])->assertOk();
    }

    public function test_numeric_date_time_boolean_and_multiple_select_values_are_typed(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        [$definition, $version, $integerQuestion] = $this->publishedTemplate($organization, $admin, 'offshore-helideck', 'integer');
        $section = $version->sections()->firstOrFail();
        $dateQuestion = $section->questions()->create(['stable_key' => fake()->uuid(), 'label' => 'Date prompt', 'field_type' => 'date', 'position' => 2]);
        $timeQuestion = $section->questions()->create(['stable_key' => fake()->uuid(), 'label' => 'Time prompt', 'field_type' => 'time', 'position' => 3]);
        $multiQuestion = $section->questions()->create(['stable_key' => fake()->uuid(), 'label' => 'Options prompt', 'field_type' => 'multi_select', 'position' => 4]);
        $multiQuestion->options()->create(['value' => 'one', 'label' => 'One', 'position' => 1]);
        $multiQuestion->options()->create(['value' => 'two', 'label' => 'Two', 'position' => 2]);
        $this->actingAs($admin, 'web');
        $surveyId = $this->createSurvey($organization, $definition, $version);
        $path = $this->surveysPath($organization)."/{$surveyId}/answers";

        $this->putJson($path, ['answers' => [['question_public_id' => $integerQuestion->public_id, 'value' => 1.5]]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => $dateQuestion->public_id, 'value' => '2026-02-30']]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => $timeQuestion->public_id, 'value' => '24:00']]])->assertUnprocessable();
        $this->putJson($path, ['answers' => [['question_public_id' => $multiQuestion->public_id, 'value' => ['one', 'missing']]]])->assertUnprocessable();

        $this->putJson($path, ['answers' => [
            ['question_public_id' => $integerQuestion->public_id, 'value' => 12],
            ['question_public_id' => $dateQuestion->public_id, 'value' => '2026-02-28'],
            ['question_public_id' => $timeQuestion->public_id, 'value' => '23:59'],
            ['question_public_id' => $multiQuestion->public_id, 'value' => ['two', 'one']],
        ]])->assertOk()
            ->assertJsonPath("data.answers.{$integerQuestion->public_id}.value", 12)
            ->assertJsonPath("data.answers.{$dateQuestion->public_id}.value", '2026-02-28')
            ->assertJsonPath("data.answers.{$timeQuestion->public_id}.value", '23:59')
            ->assertJsonPath("data.answers.{$multiQuestion->public_id}.value", ['two', 'one']);
    }

    public function test_completion_requires_required_answers_and_completed_surveys_are_read_only(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        [$definition, $version, $question] = $this->publishedTemplate($organization, $admin, 'onshore-heliport', 'boolean');
        $this->actingAs($admin, 'web');
        $surveyId = $this->createSurvey($organization, $definition, $version);
        $path = $this->surveysPath($organization)."/{$surveyId}/answers";

        $this->putJson($path, ['status' => 'completed', 'answers' => []])->assertUnprocessable();
        $this->putJson($path, ['status' => 'completed', 'answers' => [['question_public_id' => $question->public_id, 'value' => false]]])
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath("data.answers.{$question->public_id}.value", false);
        $this->putJson($path, ['answers' => [['question_public_id' => $question->public_id, 'value' => true]]])->assertStatus(409);
    }

    public function test_assignments_must_belong_to_the_same_active_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = $this->addMember($organization, 'organization_admin');
        $foreignMember = $this->addMember($otherOrganization, 'surveyor');
        [$definition, $version] = $this->publishedTemplate($organization, $admin, 'offshore-helideck', 'text');
        $this->actingAs($admin, 'web')->postJson($this->surveysPath($organization), [
            'checklist_public_id' => $definition->public_id, 'version_number' => $version->version_number,
            'site_name' => 'Site', 'assigned_to_user_id' => $foreignMember->getKey(),
        ])->assertUnprocessable();
    }

    /** @return array{0: ChecklistDefinition, 1: ChecklistVersion, 2: ChecklistQuestion} */
    private function publishedTemplate(Organization $organization, User $publisher, string $surveyTypeSlug, string $fieldType, array $optionValues = []): array
    {
        $surveyType = $organization->surveyTypes()->firstOrCreate(
            ['slug' => $surveyTypeSlug],
            ['name' => $surveyTypeSlug, 'is_active' => true],
        );
        $definition = $organization->checklistDefinitions()->create([
            'survey_type_id' => $surveyType->getKey(), 'name' => "{$surveyTypeSlug} template",
        ]);
        $version = $definition->versions()->create([
            'version_number' => 1, 'status' => 'published', 'published_at' => now(), 'published_by' => $publisher->getKey(),
        ]);
        $section = $version->sections()->create(['title' => 'Section', 'position' => 1]);
        $question = $section->questions()->create([
            'stable_key' => fake()->uuid(), 'label' => 'Source-backed question', 'field_type' => $fieldType,
            'is_required' => true, 'position' => 1,
        ]);
        foreach ($optionValues as $position => $value) {
            $question->options()->create(['value' => $value, 'label' => ucfirst($value), 'position' => $position + 1]);
        }

        return [$definition, $version, $question];
    }

    private function addMember(Organization $organization, string $roleSlug): User
    {
        $user = User::factory()->create();
        OrganizationMembership::query()->create([
            'organization_id' => $organization->getKey(), 'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->getKey(), 'status' => 'active',
        ]);

        return $user;
    }

    private function createSurvey(Organization $organization, ChecklistDefinition $definition, ChecklistVersion $version): string
    {
        return $this->postJson($this->surveysPath($organization), [
            'checklist_public_id' => $definition->public_id, 'version_number' => $version->version_number, 'site_name' => 'Test site',
        ])->assertCreated()->json('data.public_id');
    }

    private function surveysPath(Organization $organization): string
    {
        return '/api/organizations/'.$organization->public_id.'/surveys';
    }
}
