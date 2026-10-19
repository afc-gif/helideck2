<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSurveyAnswersRequest;
use App\Http\Requests\StoreSurveyInstanceRequest;
use App\Models\ChecklistDefinition;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistTableBlock;
use App\Models\ChecklistTableColumn;
use App\Models\ChecklistVersion;
use App\Models\Organization;
use App\Models\SurveyAnswer;
use App\Models\SurveyInstance;
use App\Models\SurveyTableCell;
use App\Models\SurveyTableRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SurveyInstanceController extends Controller
{
    private const SURVEY_TYPES = ['offshore-helideck', 'onshore-heliport'];

    public function templates(string $publicId): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $templates = $organization->checklistDefinitions()
            ->with(['surveyType', 'versions' => fn ($query) => $query->where('status', 'published')->orderBy('version_number')])
            ->whereHas('surveyType', fn (Builder $query) => $query->whereIn('slug', self::SURVEY_TYPES))
            ->whereHas('versions', fn (Builder $query) => $query->where('status', 'published'))
            ->orderBy('name')
            ->get()
            ->map(fn (ChecklistDefinition $definition): array => [
                'public_id' => $definition->public_id,
                'name' => $definition->name,
                'survey_type' => ['slug' => $definition->surveyType->slug, 'name' => $definition->surveyType->name],
                'versions' => $definition->versions->map(fn (ChecklistVersion $version): array => [
                    'version_number' => $version->version_number,
                    'published_at' => $version->published_at?->toISOString(),
                ])->values(),
            ])->values();

        return response()->json(['data' => $templates]);
    }

    public function index(string $publicId): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $surveys = $this->visibleSurveys($organization)
            ->with(['checklistDefinition.surveyType', 'checklistVersion', 'assignee:id,name'])
            ->latest('started_at')
            ->get()
            ->map(fn (SurveyInstance $survey): array => $this->surveySummary($survey));

        return response()->json(['data' => $surveys]);
    }

    public function store(StoreSurveyInstanceRequest $request, string $publicId): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $user = $request->user();
        $canManage = $this->canManage($organization, $user);

        $definition = $organization->checklistDefinitions()
            ->where('public_id', $request->validated('checklist_public_id'))
            ->whereHas('surveyType', fn (Builder $query) => $query->whereIn('slug', self::SURVEY_TYPES))
            ->firstOrFail();
        $version = $definition->versions()
            ->where('version_number', $request->integer('version_number'))
            ->where('status', 'published')
            ->firstOrFail();

        $assigneeId = $request->validated('assigned_to_user_id');
        if (! $canManage && $assigneeId !== null && (int) $assigneeId !== $user->getKey()) {
            abort(403);
        }
        if (! $canManage || $assigneeId === null) {
            $assigneeId = $user->getKey();
        }
        $this->assertActiveOrganizationMember($organization, (int) $assigneeId);

        $survey = $organization->surveys()->create([
            'checklist_definition_id' => $definition->getKey(),
            'checklist_version_id' => $version->getKey(),
            'checklist_name_snapshot' => $definition->name,
            'survey_type_slug_snapshot' => $definition->surveyType()->value('slug'),
            'site_name' => $request->validated('site_name'),
            'assigned_to_user_id' => $assigneeId,
            'created_by' => $user->getKey(),
            'status' => 'in_progress',
        ]);

        return response()->json(['data' => $this->surveyData($this->surveyQuery($organization)->findOrFail($survey->getKey()))], 201);
    }

    public function show(string $publicId, string $surveyPublicId): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $survey = $this->visibleSurveys($organization)
            ->where('public_id', $surveyPublicId)
            ->with(['checklistDefinition.surveyType', 'checklistVersion.sections.questions.options', 'checklistVersion.sections.tableBlocks.columns', 'answers.question', 'tableRows.tableBlock', 'tableRows.cells.column', 'assignee:id,name'])
            ->firstOrFail();

        return response()->json(['data' => $this->surveyData($survey)]);
    }

    public function saveAnswers(SaveSurveyAnswersRequest $request, string $publicId, string $surveyPublicId): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $survey = $this->visibleSurveys($organization)
            ->where('public_id', $surveyPublicId)
            ->with(['checklistVersion.sections.questions.options', 'checklistVersion.sections.tableBlocks.columns', 'answers.question', 'tableRows.tableBlock', 'tableRows.cells.column'])
            ->firstOrFail();

        if ($survey->status === 'completed') {
            return response()->json(['message' => 'Completed surveys are read-only.'], 409);
        }

        $answers = $request->validated('answers');
        $questionsByPublicId = $survey->checklistVersion->sections
            ->flatMap(fn ($section) => $section->questions)
            ->keyBy('public_id');

        foreach ($answers as $index => $answerData) {
            $question = $questionsByPublicId->get($answerData['question_public_id']);
            if (! $question) {
                throw ValidationException::withMessages(["answers.{$index}.question_public_id" => 'This question is not in the survey’s pinned checklist version.']);
            }
            $this->validateAnswerValue($question, $answerData['value'], $index);
        }

        $tableRows = $request->validated('table_rows');
        if ($tableRows !== null) {
            $this->validateTableRows($survey, $tableRows);
        }

        $newStatus = $request->validated('status', 'in_progress');
        if ($newStatus === 'completed') {
            $answerValues = $survey->answers->mapWithKeys(fn (SurveyAnswer $answer): array => [
                $answer->question->public_id => $answer->value,
            ])->all();
            foreach ($answers as $answerData) {
                $answerValues[$answerData['question_public_id']] = $answerData['value'];
            }
            foreach ($questionsByPublicId as $question) {
                if ($question->is_required && $question->field_type !== 'informational' && ! $this->hasAnswerValue($answerValues[$question->public_id] ?? null)) {
                    throw ValidationException::withMessages(['answers' => "Required question ‘{$question->label}’ needs an answer before completing this survey."]);
                }
            }
        }

        DB::transaction(function () use ($answers, $tableRows, $survey, $newStatus, $request): void {
            $lockedSurvey = SurveyInstance::query()->whereKey($survey->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedSurvey->status === 'completed') {
                abort(409, 'Completed surveys are read-only.');
            }
            foreach ($answers as $answerData) {
                $question = ChecklistQuestion::query()->where('public_id', $answerData['question_public_id'])->firstOrFail();
                $lockedSurvey->answers()->updateOrCreate(
                    ['checklist_question_id' => $question->getKey()],
                    [
                        'value' => $answerData['value'],
                        'comment' => $answerData['comment'] ?? null,
                        'updated_by' => $request->user()->getKey(),
                    ],
                );
            }
            if ($tableRows !== null) {
                $this->replaceSurveyTableRows($lockedSurvey, $tableRows, $request->user()->getKey());
            }
            $lockedSurvey->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : null,
            ]);
        });

        return response()->json(['data' => $this->surveyData($this->surveyQuery($organization)->findOrFail($survey->getKey()))]);
    }

    private function organizationForSurveys(string $publicId): Organization
    {
        $organization = Organization::query()->visibleTo(request()->user())->where('public_id', $publicId)->firstOrFail();
        Gate::authorize('viewSurveys', $organization);

        return $organization;
    }

    private function visibleSurveys(Organization $organization): Builder
    {
        $query = $organization->surveys();
        if (! $this->canManage($organization, request()->user())) {
            $query->where('assigned_to_user_id', request()->user()->getKey());
        }

        return $query->getQuery();
    }

    private function canManage(Organization $organization, User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasOrganizationPermission($organization->getKey(), 'inspections.manage');
    }

    private function assertActiveOrganizationMember(Organization $organization, int $userId): void
    {
        $assignee = $organization->memberships()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with('user')
            ->first()?->user;
        abort_unless($assignee instanceof User, 422, 'The assignee must be an active member of this organization.');
        abort_unless(
            $this->canManage($organization, $assignee)
                || $assignee->hasOrganizationPermission($organization->getKey(), 'inspections.assigned.view'),
            422,
            'The assignee must have permission to view assigned surveys.',
        );
    }

    private function surveyQuery(Organization $organization): Builder
    {
        return $organization->surveys()->with([
            'checklistDefinition.surveyType',
            'checklistVersion.sections.questions.options',
            'checklistVersion.sections.tableBlocks.columns',
            'answers.question',
            'tableRows.tableBlock',
            'tableRows.cells.column',
            'assignee:id,name',
        ])->getQuery();
    }

    /** @return array<string, mixed> */
    private function surveySummary(SurveyInstance $survey): array
    {
        return [
            'public_id' => $survey->public_id,
            'site_name' => $survey->site_name,
            'status' => $survey->status,
            'started_at' => $survey->started_at?->toISOString(),
            'completed_at' => $survey->completed_at?->toISOString(),
            'checklist' => [
                'public_id' => $survey->checklistDefinition->public_id,
                'name' => $survey->checklist_name_snapshot,
                'survey_type' => $survey->survey_type_slug_snapshot,
                'version_number' => $survey->checklistVersion->version_number,
            ],
            'assignee' => $survey->assignee ? ['id' => $survey->assignee->getKey(), 'name' => $survey->assignee->name] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function surveyData(SurveyInstance $survey): array
    {
        $data = $this->surveySummary($survey);
        $data['sections'] = $survey->checklistVersion->sections->map(fn ($section): array => [
            'public_id' => $section->public_id,
            'title' => $section->title,
            'description' => $section->description,
            'source_provenance' => $this->provenanceData($section),
            'questions' => $section->questions->map(fn (ChecklistQuestion $question): array => [
                'public_id' => $question->public_id,
                'stable_key' => $question->stable_key,
                'label' => $question->label,
                'description' => $question->description,
                'instructions' => $question->instructions,
                'field_type' => $question->field_type,
                'is_required' => $question->is_required,
                'unit' => $question->unit,
                'options' => $question->options->map(fn ($option): array => ['value' => $option->value, 'label' => $option->label])->values(),
                'source_provenance' => $this->provenanceData($question),
            ])->values(),
            'table_blocks' => $section->tableBlocks->sortBy('position')->values()->map(fn (ChecklistTableBlock $block): array => [
                'public_id' => $block->public_id,
                'stable_key' => $block->stable_key,
                'title' => $block->title,
                'description' => $block->description,
                'columns' => $block->columns->sortBy('position')->values()->map(fn (ChecklistTableColumn $column): array => [
                    'public_id' => $column->public_id,
                    'stable_key' => $column->stable_key,
                    'label' => $column->label,
                    'source_label' => $column->source_label,
                    'data_type' => $column->data_type,
                    'unit' => $column->unit,
                    'source_provenance' => $this->provenanceData($column),
                ])->all(),
            ])->all(),
        ])->values();
        $data['answers'] = $survey->answers->mapWithKeys(fn (SurveyAnswer $answer): array => [
            $answer->question->public_id => ['value' => $answer->value, 'comment' => $answer->comment],
        ]);
        $data['table_rows'] = $survey->tableRows->groupBy(fn (SurveyTableRow $row): string => $row->tableBlock->public_id)
            ->map(fn ($rows) => $rows->sortBy('position')->values()->map(fn (SurveyTableRow $row): array => [
                'public_id' => $row->public_id,
                'cells' => $row->cells->mapWithKeys(fn (SurveyTableCell $cell): array => [
                    $cell->column->public_id => ['value' => $cell->value, 'comment' => $cell->comment],
                ]),
            ]));

        return $data;
    }

    private function validateAnswerValue(ChecklistQuestion $question, mixed $value, int $index): void
    {
        $field = "answers.{$index}.value";
        $valid = match ($question->field_type) {
            'text', 'textarea' => $value === null || (is_string($value) && mb_strlen($value) <= 10000),
            'integer' => $value === null || is_int($value),
            'decimal' => $value === null || ((is_int($value) || is_float($value)) && is_finite((float) $value)),
            'date' => $value === null || $this->matchesDate($value),
            'time' => $value === null || (is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1),
            'boolean' => $value === null || is_bool($value),
            'single_select' => $value === null || (is_string($value) && $question->options->contains('value', $value)),
            'multi_select' => $value === null || (is_array($value) && collect($value)->every(fn ($option): bool => is_string($option)) && count($value) === count(array_unique($value)) && collect($value)->every(fn ($option): bool => $question->options->contains('value', $option))),
            'informational' => $value === null,
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([$field => 'The answer does not match this question type or its published response options.']);
        }
    }

    /** @param array<int, array<string, mixed>> $tables */
    private function validateTableRows(SurveyInstance $survey, array $tables): void
    {
        $blocks = $survey->checklistVersion->sections->flatMap(fn ($section) => $section->tableBlocks)->keyBy('public_id');
        $seenBlocks = [];
        foreach ($tables as $tableIndex => $tableData) {
            $block = $blocks->get($tableData['table_public_id']);
            if (! $block || isset($seenBlocks[$tableData['table_public_id']])) {
                throw ValidationException::withMessages(["table_rows.{$tableIndex}.table_public_id" => 'This table is not uniquely present in the survey’s pinned checklist version.']);
            }
            $seenBlocks[$tableData['table_public_id']] = true;
            $columns = $block->columns->keyBy('public_id');
            foreach ($tableData['rows'] as $rowIndex => $row) {
                $seenColumns = [];
                foreach ($row['cells'] as $cellIndex => $cell) {
                    $column = $columns->get($cell['column_public_id']);
                    $errorKey = "table_rows.{$tableIndex}.rows.{$rowIndex}.cells.{$cellIndex}";
                    if (! $column || isset($seenColumns[$cell['column_public_id']])) {
                        throw ValidationException::withMessages(["{$errorKey}.column_public_id" => 'This column is not uniquely present in the pinned table.']);
                    }
                    $seenColumns[$cell['column_public_id']] = true;
                    $value = $cell['value'] ?? null;
                    $valid = match ($column->data_type) {
                        'text' => $value === null || (is_string($value) && mb_strlen($value) <= 10000),
                        'integer' => $value === null || is_int($value),
                        'decimal' => $value === null || ((is_int($value) || is_float($value)) && is_finite((float) $value)),
                        'date' => $value === null || $this->matchesDate($value),
                        'time' => $value === null || (is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1),
                        'boolean' => $value === null || is_bool($value),
                        default => false,
                    };
                    if (! $valid) {
                        throw ValidationException::withMessages(["{$errorKey}.value" => 'The cell value does not match the pinned column data type.']);
                    }
                }
            }
        }
    }

    /** @param array<int, array<string, mixed>> $tables */
    private function replaceSurveyTableRows(SurveyInstance $survey, array $tables, int $userId): void
    {
        $survey->loadMissing('checklistVersion.sections.tableBlocks.columns');
        if ($tables === []) {
            $survey->tableRows()->delete();

            return;
        }
        $blocks = $survey->checklistVersion->sections->flatMap(fn ($section) => $section->tableBlocks)->keyBy('public_id');
        foreach ($tables as $tableData) {
            /** @var ChecklistTableBlock $block */
            $block = $blocks->get($tableData['table_public_id']);
            $survey->tableRows()->where('checklist_table_block_id', $block->getKey())->delete();
            foreach ($tableData['rows'] as $position => $rowData) {
                $row = $survey->tableRows()->create([
                    'checklist_version_id' => $survey->checklist_version_id,
                    'checklist_table_block_id' => $block->getKey(),
                    'position' => $position + 1,
                ]);
                $columns = $block->columns->keyBy('public_id');
                foreach ($rowData['cells'] as $cellData) {
                    /** @var ChecklistTableColumn $column */
                    $column = $columns->get($cellData['column_public_id']);
                    $row->cells()->create([
                        'checklist_table_block_id' => $block->getKey(),
                        'checklist_table_column_id' => $column->getKey(),
                        'value' => $cellData['value'] ?? null,
                        'comment' => $cellData['comment'] ?? null,
                        'updated_by' => $userId,
                    ]);
                }
            }
        }
    }

    private function provenanceData(object $record): array
    {
        return [
            'document_identifier' => $record->source_document_identifier,
            'wording' => $record->source_wording,
            'locator' => $record->source_locator,
            'review_status' => $record->source_review_status,
        ];
    }

    private function matchesDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function hasAnswerValue(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
