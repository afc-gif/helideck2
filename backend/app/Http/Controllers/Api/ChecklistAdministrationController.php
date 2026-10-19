<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveChecklistVersionRequest;
use App\Http\Requests\StoreChecklistDefinitionRequest;
use App\Http\Requests\UpdateChecklistDefinitionRequest;
use App\Models\AnswerOption;
use App\Models\ChecklistDefinition;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Models\Organization;
use App\Models\SurveyType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ChecklistAdministrationController extends Controller
{
    /** @var array<string, string> */
    private const SURVEY_TYPES = [
        'offshore-helideck' => 'Offshore helideck',
        'onshore-heliport' => 'Onshore heliport',
    ];

    public function index(string $publicId): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $surveyTypes = $this->ensureSurveyTypes($organization);

        $checklists = $organization->checklistDefinitions()
            ->with(['surveyType', 'versions.sections.questions.options'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'survey_types' => $surveyTypes->map(fn (SurveyType $type): array => [
                    'slug' => $type->slug,
                    'name' => $type->name,
                ])->values(),
                'checklists' => $checklists->map(fn (ChecklistDefinition $definition): array => $this->definitionData($definition))->values(),
            ],
        ]);
    }

    public function store(StoreChecklistDefinitionRequest $request, string $publicId): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $surveyType = $this->ensureSurveyTypes($organization)->firstWhere('slug', $request->validated('survey_type'));

        $definition = DB::transaction(function () use ($organization, $surveyType, $request): ChecklistDefinition {
            $definition = $organization->checklistDefinitions()->create([
                'survey_type_id' => $surveyType->getKey(),
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
            ]);

            $definition->versions()->create(['version_number' => 1, 'status' => 'draft']);

            return $definition;
        });

        $definition->load(['surveyType', 'versions.sections.questions.options']);

        return response()->json(['data' => $this->definitionData($definition)], 201);
    }

    public function show(string $publicId, string $checklistPublicId): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $definition = $this->definitionQuery($organization)
            ->where('public_id', $checklistPublicId)
            ->firstOrFail();

        return response()->json(['data' => $this->definitionData($definition)]);
    }

    public function update(UpdateChecklistDefinitionRequest $request, string $publicId, string $checklistPublicId): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $definition = $organization->checklistDefinitions()->where('public_id', $checklistPublicId)->firstOrFail();
        $definition->update($request->validated());
        $definition->load(['surveyType', 'versions.sections.questions.options']);

        return response()->json(['data' => $this->definitionData($definition)]);
    }

    public function createVersion(string $publicId, string $checklistPublicId): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $definition = $organization->checklistDefinitions()->where('public_id', $checklistPublicId)->firstOrFail();

        $result = DB::transaction(function () use ($definition): ChecklistVersion|JsonResponse {
            $lockedDefinition = ChecklistDefinition::query()->whereKey($definition->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedDefinition->versions()->where('status', 'draft')->exists()) {
                return response()->json(['message' => 'This checklist already has a draft version. Save or publish it before creating another.'], 409);
            }

            $previousVersion = $lockedDefinition->versions()
                ->with(['sections.questions.options'])
                ->orderByDesc('version_number')
                ->first();
            $newVersion = $lockedDefinition->versions()->create([
                'version_number' => ((int) $lockedDefinition->versions()->max('version_number')) + 1,
                'status' => 'draft',
            ]);

            if ($previousVersion) {
                $this->copyVersionContent($previousVersion, $newVersion);
            }

            return $newVersion;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $definition->load(['surveyType', 'versions.sections.questions.options']);

        return response()->json(['data' => $this->definitionData($definition)], 201);
    }

    public function saveVersion(SaveChecklistVersionRequest $request, string $publicId, string $checklistPublicId, int $versionNumber): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $definition = $organization->checklistDefinitions()->where('public_id', $checklistPublicId)->firstOrFail();
        $version = $definition->versions()->where('version_number', $versionNumber)->firstOrFail();

        if ($version->status !== 'draft') {
            return response()->json(['message' => 'Published checklist versions are immutable. Create a new draft version to make changes.'], 409);
        }

        DB::transaction(function () use ($version, $request): void {
            $lockedVersion = ChecklistVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedVersion->status !== 'draft') {
                abort(409, 'Published checklist versions are immutable. Create a new draft version to make changes.');
            }

            $lockedVersion->sections()->delete();

            foreach ($request->validated('sections') as $sectionPosition => $sectionData) {
                $section = $lockedVersion->sections()->create([
                    'title' => $sectionData['title'],
                    'description' => $sectionData['description'] ?? null,
                    'position' => $sectionPosition + 1,
                ]);

                foreach ($sectionData['questions'] as $questionPosition => $questionData) {
                    $question = $section->questions()->create([
                        'stable_key' => $questionData['stable_key'] ?? (string) Str::uuid(),
                        'label' => $questionData['label'],
                        'description' => $questionData['description'] ?? null,
                        'instructions' => $questionData['instructions'] ?? null,
                        'field_type' => $questionData['field_type'],
                        'is_required' => $questionData['is_required'],
                        'unit' => $questionData['unit'] ?? null,
                        'position' => $questionPosition + 1,
                    ]);

                    foreach ($questionData['options'] as $optionPosition => $optionData) {
                        $question->options()->create([
                            'value' => $optionData['value'],
                            'label' => $optionData['label'],
                            'position' => $optionPosition + 1,
                        ]);
                    }
                }
            }
        });

        $definition = $this->definitionQuery($organization)->where('public_id', $checklistPublicId)->firstOrFail();

        return response()->json(['data' => $this->definitionData($definition)]);
    }

    public function publishVersion(string $publicId, string $checklistPublicId, int $versionNumber): JsonResponse
    {
        $organization = $this->organizationForManagement($publicId);
        $definition = $organization->checklistDefinitions()->where('public_id', $checklistPublicId)->firstOrFail();
        $version = $definition->versions()->where('version_number', $versionNumber)->firstOrFail();

        if ($version->status !== 'draft') {
            return response()->json(['message' => 'Only draft checklist versions can be published.'], 409);
        }

        if (! $version->sections()->whereHas('questions')->exists()) {
            return response()->json(['message' => 'Add at least one section with a question before publishing.'], 422);
        }

        $version->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => request()->user()->getKey(),
        ]);

        $definition = $this->definitionQuery($organization)->where('public_id', $checklistPublicId)->firstOrFail();

        return response()->json(['data' => $this->definitionData($definition)]);
    }

    private function organizationForManagement(string $publicId): Organization
    {
        $organization = Organization::query()
            ->visibleTo(request()->user())
            ->where('public_id', $publicId)
            ->firstOrFail();

        Gate::authorize('manageChecklists', $organization);

        return $organization;
    }

    /** @return Collection<int, SurveyType> */
    private function ensureSurveyTypes(Organization $organization): Collection
    {
        return collect(self::SURVEY_TYPES)->map(function (string $name, string $slug) use ($organization): SurveyType {
            return $organization->surveyTypes()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => null, 'is_active' => true],
            );
        });
    }

    /** @return HasMany<ChecklistDefinition, Organization> */
    private function definitionQuery(Organization $organization): HasMany
    {
        return $organization->checklistDefinitions()
            ->with(['surveyType', 'versions.sections.questions.options']);
    }

    /** @return array<string, mixed> */
    private function definitionData(ChecklistDefinition $definition): array
    {
        $versions = $definition->versions->sortBy('version_number')->values();

        return [
            'public_id' => $definition->public_id,
            'name' => $definition->name,
            'description' => $definition->description,
            'survey_type' => [
                'slug' => $definition->surveyType->slug,
                'name' => $definition->surveyType->name,
            ],
            'versions' => $versions->map(fn (ChecklistVersion $version): array => $this->versionData($version))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionData(ChecklistVersion $version): array
    {
        return [
            'version_number' => $version->version_number,
            'status' => $version->status,
            'published_at' => $version->published_at?->toISOString(),
            'sections' => $version->sections->sortBy('position')->values()->map(fn (ChecklistSection $section): array => [
                'title' => $section->title,
                'description' => $section->description,
                'questions' => $section->questions->sortBy('position')->values()->map(fn (ChecklistQuestion $question): array => [
                    'stable_key' => $question->stable_key,
                    'label' => $question->label,
                    'description' => $question->description,
                    'instructions' => $question->instructions,
                    'field_type' => $question->field_type,
                    'is_required' => $question->is_required,
                    'unit' => $question->unit,
                    'options' => $question->options->sortBy('position')->values()->map(fn (AnswerOption $option): array => [
                        'value' => $option->value,
                        'label' => $option->label,
                    ])->all(),
                ])->all(),
            ])->all(),
        ];
    }

    private function copyVersionContent(ChecklistVersion $source, ChecklistVersion $target): void
    {
        foreach ($source->sections as $sourceSection) {
            $section = $target->sections()->create([
                'title' => $sourceSection->title,
                'description' => $sourceSection->description,
                'position' => $sourceSection->position,
            ]);

            foreach ($sourceSection->questions as $sourceQuestion) {
                $question = $section->questions()->create([
                    'stable_key' => $sourceQuestion->stable_key,
                    'label' => $sourceQuestion->label,
                    'description' => $sourceQuestion->description,
                    'instructions' => $sourceQuestion->instructions,
                    'field_type' => $sourceQuestion->field_type,
                    'is_required' => $sourceQuestion->is_required,
                    'validation_rules' => $sourceQuestion->validation_rules,
                    'visibility_condition' => $sourceQuestion->visibility_condition,
                    'comment_requirement' => $sourceQuestion->comment_requirement,
                    'unit' => $sourceQuestion->unit,
                    'position' => $sourceQuestion->position,
                    'is_sample' => $sourceQuestion->is_sample,
                ]);

                foreach ($sourceQuestion->options as $sourceOption) {
                    $question->options()->create([
                        'value' => $sourceOption->value,
                        'label' => $sourceOption->label,
                        'position' => $sourceOption->position,
                    ]);
                }
            }
        }
    }
}
