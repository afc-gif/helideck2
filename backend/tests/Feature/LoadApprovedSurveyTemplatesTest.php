<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SurveyInstance;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LoadApprovedSurveyTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_loader_dry_run_is_read_only_and_loads_two_separate_drafts_when_applied(): void
    {
        $organization = Organization::factory()->create(['name' => 'ARTSCI', 'slug' => 'artsci']);

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci'])
            ->expectsOutputToContain('Would create a offshore-helideck definition')
            ->assertSuccessful();
        $this->assertSame(0, $organization->checklistDefinitions()->count());
        $this->assertSame(0, $organization->surveyTypes()->count());

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])
            ->assertSuccessful();

        $definitions = $organization->checklistDefinitions()->with(['surveyType', 'versions.sections.questions', 'versions.sections.tableBlocks.columns'])->get();
        $this->assertCount(2, $definitions);
        $offshore = $definitions->firstWhere('surveyType.slug', 'offshore-helideck');
        $onshore = $definitions->firstWhere('surveyType.slug', 'onshore-heliport');
        $this->assertNotNull($offshore);
        $this->assertNotNull($onshore);
        $this->assertSame('draft', $offshore->versions->sole()->status);
        $this->assertSame('draft', $onshore->versions->sole()->status);
        $this->assertSame('HELIDECK REPORT FSO TOPAZ.docx', $offshore->versions->sole()->sections->first()->source_document_identifier);
        $this->assertSame('review_required', $offshore->versions->sole()->sections->first()->source_review_status);
        $this->assertSame('Heliport inspection report NORTH BANK.docx', $onshore->versions->sole()->sections->first()->source_document_identifier);
        $this->assertSame(3, $offshore->versions->sole()->sections->sum(fn ($section) => $section->tableBlocks->count()));
        $this->assertSame(4, $onshore->versions->sole()->sections->sum(fn ($section) => $section->tableBlocks->count()));
        $this->assertSame(0, SurveyInstance::query()->count());
        $this->assertSame(0, $offshore->versions->sole()->sections->sum(fn ($section) => $section->tableBlocks->sum(fn ($block) => $block->surveyRows()->count())));
    }

    public function test_loader_preserves_full_long_approved_question_labels(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $manifest = json_decode(file_get_contents(resource_path('survey-templates/approved/offshore-helideck.json')), true, flags: JSON_THROW_ON_ERROR);
        $labels = collect($manifest['sections'])->flatMap(fn (array $section): array => array_column($section['questions'], 'label'));
        $longestLabel = $labels->sortByDesc(fn (string $label): int => mb_strlen($label))->first();
        $this->assertGreaterThan(255, mb_strlen($longestLabel));

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])->assertSuccessful();

        $savedLabels = $organization->checklistDefinitions()->with('versions.sections.questions')->get()
            ->flatMap(fn ($definition) => $definition->versions->flatMap(fn ($version) => $version->sections->flatMap(fn ($section) => $section->questions->pluck('label'))));
        $this->assertTrue($savedLabels->contains($longestLabel));
    }

    public function test_failed_template_load_rolls_back_every_template_write(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $injectedFailure = false;
        DB::listen(function (QueryExecuted $query) use (&$injectedFailure): void {
            if (! $injectedFailure && str_contains(strtolower($query->sql), 'insert into "checklist_questions"')) {
                $injectedFailure = true;
                throw new RuntimeException('Injected checklist question insert failure.');
            }
        });

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])
            ->expectsOutputToContain('all changes from this run were rolled back')
            ->assertExitCode(1);

        $this->assertTrue($injectedFailure);
        $this->assertSame(0, $organization->checklistDefinitions()->count());
        $this->assertSame(0, $organization->surveyTypes()->count());
    }

    public function test_loader_is_idempotent_reuses_the_existing_fso_draft_and_never_publishes(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $surveyType = $organization->surveyTypes()->create(['name' => 'Offshore helideck', 'slug' => 'offshore-helideck']);
        $existing = $organization->checklistDefinitions()->create([
            'survey_type_id' => $surveyType->getKey(),
            'name' => 'FSO TOPAZ Offshore Helideck',
        ]);
        $existing->versions()->create(['version_number' => 1, 'status' => 'draft']);

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])->assertSuccessful();
        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])->expectsOutputToContain('matching draft v1 is already loaded')->assertSuccessful();

        $this->assertSame(2, $organization->checklistDefinitions()->count());
        $this->assertSame(1, $existing->versions()->count());
        $this->assertSame('draft', $existing->versions()->sole()->status);
    }

    public function test_nonempty_matching_draft_is_not_replaced_without_explicit_flag(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $surveyType = $organization->surveyTypes()->create(['name' => 'Offshore helideck', 'slug' => 'offshore-helideck']);
        $definition = $organization->checklistDefinitions()->create(['survey_type_id' => $surveyType->getKey(), 'name' => 'Existing FSO TOPAZ checklist']);
        $version = $definition->versions()->create(['version_number' => 1, 'status' => 'draft']);
        $version->sections()->create(['title' => 'Admin work to retain', 'position' => 1]);

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])
            ->expectsOutputToContain('replacement flag required')
            ->assertExitCode(1);
        $this->assertSame('Admin work to retain', $version->sections()->sole()->title);
        $this->assertSame(1, $organization->checklistDefinitions()->count());

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true, '--replace-draft' => true])->assertSuccessful();
        $this->assertSame(2, $organization->checklistDefinitions()->count());
        $this->assertSame(27, $version->fresh()->sections()->count());
        $this->assertSame('draft', $version->fresh()->status);
    }

    public function test_loader_only_writes_to_the_explicit_organization_and_requires_a_real_target(): void
    {
        $target = Organization::factory()->create(['slug' => 'artsci']);
        $other = Organization::factory()->create(['slug' => 'another-org']);

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])->assertSuccessful();
        $this->assertSame(2, $target->checklistDefinitions()->count());
        $this->assertSame(0, $other->checklistDefinitions()->count());
        $this->artisan('survey-templates:load-approved', ['organization' => 'missing-org', '--apply' => true])
            ->expectsOutputToContain('Organization not found')
            ->assertExitCode(1);
        $this->assertSame(0, $other->checklistDefinitions()->count());
    }

    public function test_slug_lookup_uses_only_the_slug_column_and_does_not_form_a_uuid_or_predicate(): void
    {
        Organization::factory()->create(['slug' => 'artsci']);
        $organizationQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$organizationQueries): void {
            if (str_contains(strtolower($query->sql), 'from "organizations"')) {
                $organizationQueries[] = strtolower($query->sql);
            }
        });

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci'])
            ->expectsOutputToContain('Would create a offshore-helideck definition')
            ->assertSuccessful();

        $lookup = collect($organizationQueries)->first(fn (string $sql): bool => str_contains($sql, 'where'));
        $this->assertNotNull($lookup);
        $this->assertStringContainsString('"slug"', $lookup);
        $this->assertStringNotContainsString('"public_id"', $lookup);
        $this->assertStringNotContainsString(' or ', $lookup);
    }

    public function test_uuid_argument_resolves_organization_by_public_id_only(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $organizationQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$organizationQueries): void {
            if (str_contains(strtolower($query->sql), 'from "organizations"')) {
                $organizationQueries[] = strtolower($query->sql);
            }
        });

        $this->artisan('survey-templates:load-approved', ['organization' => $organization->public_id])
            ->expectsOutputToContain('Would create a offshore-helideck definition')
            ->assertSuccessful();

        $lookup = collect($organizationQueries)->first(fn (string $sql): bool => str_contains($sql, 'where'));
        $this->assertNotNull($lookup);
        $this->assertStringContainsString('"public_id"', $lookup);
        $this->assertStringNotContainsString('"slug"', $lookup);
        $this->assertStringNotContainsString(' or ', $lookup);
        $this->assertSame(0, $organization->checklistDefinitions()->count());
    }

    public function test_loader_refuses_to_duplicate_an_unidentified_existing_checklist_of_the_same_type(): void
    {
        $organization = Organization::factory()->create(['slug' => 'artsci']);
        $surveyType = $organization->surveyTypes()->create(['name' => 'Offshore helideck', 'slug' => 'offshore-helideck']);
        $organization->checklistDefinitions()->create(['survey_type_id' => $surveyType->getKey(), 'name' => 'Existing offshore checklist']);

        $this->artisan('survey-templates:load-approved', ['organization' => 'artsci', '--apply' => true])
            ->expectsOutputToContain('No duplicate was created')
            ->assertExitCode(1);
        $this->assertSame(1, $organization->checklistDefinitions()->count());
    }

    public function test_manifests_keep_offshore_and_onshore_sections_distinct_and_source_locations_reviewable(): void
    {
        $offshore = json_decode(file_get_contents(resource_path('survey-templates/approved/offshore-helideck.json')), true, flags: JSON_THROW_ON_ERROR);
        $onshore = json_decode(file_get_contents(resource_path('survey-templates/approved/onshore-heliport.json')), true, flags: JSON_THROW_ON_ERROR);
        $offshoreText = json_encode($offshore, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $onshoreText = json_encode($onshore, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Sub-1D risk assessment', $offshoreText);
        $this->assertStringNotContainsString('Sub-1D risk assessment', $onshoreText);
        $this->assertStringContainsString('Taxiways and taxi-route criteria', $onshoreText);
        $this->assertStringNotContainsString('Taxiways and taxi-route criteria', $offshoreText);
        $this->assertStringContainsString('review_required', $offshoreText);
        $this->assertStringContainsString('"table":"16"', $onshoreText);
        $this->assertStringContainsString('Helicopter dimensions and values', $onshoreText);
        $this->assertStringContainsString('Taxiway / taxi-route type', $onshoreText);
        $this->assertStringContainsString('Foam requirement matrix', $offshoreText);
        $this->assertStringNotContainsString('OLISA KENECHUKWU', $offshoreText.$onshoreText);
        $this->assertStringNotContainsString('NOT SIGHTED', $offshoreText.$onshoreText);
        $this->assertStringNotContainsString('27 TH  July, 2026', $offshoreText.$onshoreText);
    }
}
