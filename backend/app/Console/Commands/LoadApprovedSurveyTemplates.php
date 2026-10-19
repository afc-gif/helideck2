<?php

namespace App\Console\Commands;

use App\Models\ChecklistDefinition;
use App\Models\ChecklistVersion;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

#[Signature('survey-templates:load-approved {organization : Organization slug or public ID} {--apply : Persist drafts; otherwise show the plan} {--replace-draft : Replace a different non-empty matching draft}')]
#[Description('Load approved offshore and onshore survey templates as unpublished drafts.')]
class LoadApprovedSurveyTemplates extends Command
{
    public function handle(): int
    {
        $organizationIdentifier = $this->argument('organization');
        $organizationLookup = Organization::query();

        // Keep UUID comparisons on the UUID column only. PostgreSQL may cast
        // every bound value in an OR predicate to UUID, including a slug.
        $organization = Str::isUuid($organizationIdentifier)
            ? $organizationLookup->where('public_id', $organizationIdentifier)->first()
            : $organizationLookup->where('slug', $organizationIdentifier)->first();

        if (! $organization) {
            $this->error('Organization not found. No organization was created.');

            return self::FAILURE;
        }

        try {
            $manifests = collect(['offshore-helideck', 'onshore-heliport'])
                ->map(fn (string $slug): array => $this->readManifest($slug));
        } catch (JsonException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($manifests as $manifest) {
            $plan = $this->resolvePlan($organization, $manifest);
            if ($plan['error'] !== null) {
                $this->error($plan['error']);

                return self::FAILURE;
            }
            $this->line($plan['summary']);

            if (! $this->option('apply') || $plan['already_loaded']) {
                continue;
            }
            if ($plan['nonempty_draft'] && ! $this->option('replace-draft')) {
                $this->error('A different non-empty draft exists. Review it, then rerun with --replace-draft if replacement is intended.');

                return self::FAILURE;
            }

            DB::transaction(function () use ($organization, $manifest, $plan): void {
                $definition = $plan['definition'] ?? $this->createDefinition($organization, $manifest);
                $version = $plan['version'] ?? $definition->versions()->create([
                    'version_number' => ((int) $definition->versions()->max('version_number')) + 1,
                    'status' => 'draft',
                ]);

                if ($version->status !== 'draft') {
                    throw new RuntimeException('Refusing to change a published checklist version.');
                }

                $version->sections()->delete();
                $this->writeSections($version, $manifest['sections']);
            });

            $this->info('Draft saved. It has not been published.');
        }

        if (! $this->option('apply')) {
            $this->comment('Dry run only. Pass --apply to save drafts.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function readManifest(string $slug): array
    {
        $path = resource_path("survey-templates/approved/{$slug}.json");
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read approved template definition: {$path}");
        }

        $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['survey_type'] ?? null) !== $slug || empty($manifest['sections'])) {
            throw new RuntimeException("Approved template definition {$slug} is missing sections or has a mismatched survey type.");
        }

        return $manifest;
    }

    /** @param array<string, mixed> $manifest
     * @return array{definition: ?ChecklistDefinition, version: ?ChecklistVersion, nonempty_draft: bool, already_loaded: bool, error: ?string, summary: string}
     */
    private function resolvePlan(Organization $organization, array $manifest): array
    {
        $surveyType = $organization->surveyTypes()->where('slug', $manifest['survey_type'])->first();
        $needle = $manifest['survey_type'] === 'offshore-helideck' ? 'fso topaz' : 'north bank';
        $definitions = $surveyType
            ? $organization->checklistDefinitions()->where('survey_type_id', $surveyType->getKey())->with('versions.sections')->get()
            : collect();
        $candidates = $definitions->filter(fn (ChecklistDefinition $definition): bool => Str::lower($definition->name) === Str::lower($manifest['name'])
            || Str::contains(Str::lower($definition->name), $needle)
            || $definition->versions->contains(fn (ChecklistVersion $version): bool => $version->sections->contains('source_document_identifier', $manifest['source_document_identifier']))
        )->values();

        if ($candidates->count() > 1) {
            return ['definition' => null, 'version' => null, 'nonempty_draft' => false, 'already_loaded' => false, 'error' => "Multiple {$needle} checklist definitions exist in this organization. No changes made.", 'summary' => ''];
        }

        $definition = $candidates->first();
        if (! $definition) {
            if ($definitions->isNotEmpty()) {
                return [
                    'definition' => null, 'version' => null, 'nonempty_draft' => false, 'already_loaded' => false,
                    'error' => "An existing {$manifest['survey_type']} definition could not be identified as this approved source template. No duplicate was created; review the existing definition first.",
                    'summary' => '',
                ];
            }

            return [
                'definition' => null, 'version' => null, 'nonempty_draft' => false, 'already_loaded' => false, 'error' => null,
                'summary' => "Would create a {$manifest['survey_type']} definition and version 1 draft in {$organization->name}.",
            ];
        }

        $drafts = $definition->versions()->where('status', 'draft')->get();
        if ($drafts->count() > 1) {
            return ['definition' => $definition, 'version' => null, 'nonempty_draft' => false, 'already_loaded' => false, 'error' => "More than one draft version exists for {$definition->name}. No changes made.", 'summary' => ''];
        }

        $version = $drafts->first();
        if ($version && $this->versionMatches($version, $manifest)) {
            return ['definition' => $definition, 'version' => $version, 'nonempty_draft' => false, 'already_loaded' => true, 'error' => null, 'summary' => "{$definition->name}: matching draft v{$version->version_number} is already loaded (no change)."];
        }

        if ($version) {
            $nonempty = $version->sections()->exists();

            return [
                'definition' => $definition, 'version' => $version, 'nonempty_draft' => $nonempty, 'already_loaded' => false, 'error' => null,
                'summary' => "Would update existing {$definition->name} draft v{$version->version_number}".($nonempty ? ' (non-empty; replacement flag required).' : '.'),
            ];
        }

        $nextVersion = ((int) $definition->versions()->max('version_number')) + 1;

        return [
            'definition' => $definition, 'version' => null, 'nonempty_draft' => false, 'already_loaded' => false, 'error' => null,
            'summary' => "Would create {$definition->name} draft v{$nextVersion}; published versions are retained.",
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function createDefinition(Organization $organization, array $manifest): ChecklistDefinition
    {
        $surveyType = $organization->surveyTypes()->firstOrCreate(
            ['slug' => $manifest['survey_type']],
            ['name' => $manifest['survey_type'] === 'offshore-helideck' ? 'Offshore helideck' : 'Onshore heliport', 'is_active' => true],
        );

        return $organization->checklistDefinitions()->create([
            'survey_type_id' => $surveyType->getKey(),
            'name' => $manifest['name'],
            'description' => $manifest['description'],
        ]);
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function writeSections(ChecklistVersion $version, array $sections): void
    {
        foreach ($sections as $sectionPosition => $sectionData) {
            $section = $version->sections()->create([
                'title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'position' => $sectionPosition + 1,
                ...$this->sourceAttributes($sectionData['source_provenance']),
            ]);

            foreach ($sectionData['questions'] as $questionPosition => $questionData) {
                $question = $section->questions()->create([
                    'stable_key' => $this->stableUuid($questionData['stable_key']),
                    'label' => $questionData['label'],
                    'field_type' => $questionData['field_type'],
                    'is_required' => $questionData['is_required'],
                    'unit' => $questionData['unit'] ?? null,
                    'position' => $questionPosition + 1,
                    ...$this->sourceAttributes($questionData['source_provenance']),
                ]);

                foreach ($questionData['options'] as $optionPosition => $option) {
                    $question->options()->create(['value' => $option['value'], 'label' => $option['label'], 'position' => $optionPosition + 1]);
                }
            }

            foreach ($sectionData['table_blocks'] as $blockPosition => $blockData) {
                $block = $section->tableBlocks()->create([
                    'checklist_version_id' => $version->getKey(),
                    'stable_key' => $this->stableUuid($blockData['stable_key']),
                    'title' => $blockData['title'],
                    'description' => $blockData['description'] ?? null,
                    'position' => $blockPosition + 1,
                ]);

                foreach ($blockData['columns'] as $columnPosition => $columnData) {
                    $block->columns()->create([
                        'stable_key' => $this->stableUuid($columnData['stable_key']),
                        'label' => $columnData['label'],
                        'source_label' => $columnData['source_label'],
                        'data_type' => $columnData['data_type'],
                        'unit' => $columnData['unit'],
                        'position' => $columnPosition + 1,
                        ...$this->sourceAttributes($columnData['source_provenance']),
                    ]);
                }
            }
        }
    }

    /** @param array<string, mixed> $provenance
     * @return array<string, mixed>
     */
    private function sourceAttributes(array $provenance): array
    {
        return [
            'source_document_identifier' => $provenance['document_identifier'],
            'source_wording' => $provenance['wording'],
            'source_locator' => $provenance['locator'],
            'source_review_status' => $provenance['review_status'],
        ];
    }

    private function stableUuid(string $key): string
    {
        $hex = substr(hash('sha256', $key), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    /** @param array<string, mixed> $manifest */
    private function versionMatches(ChecklistVersion $version, array $manifest): bool
    {
        $version->load('sections.questions.options', 'sections.tableBlocks.columns');
        $actualSections = $version->sections->sortBy('position')->values();
        if ($actualSections->count() !== count($manifest['sections'])) {
            return false;
        }

        foreach ($manifest['sections'] as $index => $expected) {
            $actual = $actualSections[$index];
            $sectionSource = $expected['source_provenance'];
            if ($actual->title !== $expected['title'] || $actual->source_document_identifier !== $sectionSource['document_identifier'] || $actual->source_wording !== $sectionSource['wording'] || $actual->source_locator !== $sectionSource['locator'] || $actual->source_review_status !== $sectionSource['review_status']) {
                return false;
            }

            $questions = $actual->questions->sortBy('position')->values();
            if ($questions->count() !== count($expected['questions'])) {
                return false;
            }
            foreach ($expected['questions'] as $questionIndex => $questionData) {
                $question = $questions[$questionIndex];
                if ($question->stable_key !== $this->stableUuid($questionData['stable_key']) || $question->label !== $questionData['label'] || $question->field_type !== $questionData['field_type'] || (bool) $question->is_required !== $questionData['is_required'] || $question->source_document_identifier !== $questionData['source_provenance']['document_identifier'] || $question->source_wording !== $questionData['source_provenance']['wording'] || $question->source_locator !== $questionData['source_provenance']['locator']) {
                    return false;
                }
            }

            $blocks = $actual->tableBlocks->sortBy('position')->values();
            if ($blocks->count() !== count($expected['table_blocks'])) {
                return false;
            }
            foreach ($expected['table_blocks'] as $blockIndex => $blockData) {
                $block = $blocks[$blockIndex];
                $columns = $block->columns->sortBy('position')->values();
                if ($block->stable_key !== $this->stableUuid($blockData['stable_key']) || $block->title !== $blockData['title'] || $columns->count() !== count($blockData['columns'])) {
                    return false;
                }
                foreach ($blockData['columns'] as $columnIndex => $columnData) {
                    $column = $columns[$columnIndex];
                    if ($column->stable_key !== $this->stableUuid($columnData['stable_key']) || $column->label !== $columnData['label'] || $column->source_label !== $columnData['source_label'] || $column->data_type !== $columnData['data_type'] || $column->unit !== $columnData['unit'] || $column->source_document_identifier !== $columnData['source_provenance']['document_identifier'] || $column->source_wording !== $columnData['source_provenance']['wording'] || $column->source_locator !== $columnData['source_provenance']['locator']) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
