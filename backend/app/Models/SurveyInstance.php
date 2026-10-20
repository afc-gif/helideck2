<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'checklist_definition_id', 'checklist_version_id', 'checklist_name_snapshot', 'survey_type_slug_snapshot', 'site_name', 'assigned_to_user_id', 'created_by', 'status', 'started_at', 'completed_at'])]
class SurveyInstance extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SurveyInstance $survey): void {
            $survey->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ChecklistDefinition, $this> */
    public function checklistDefinition(): BelongsTo
    {
        return $this->belongsTo(ChecklistDefinition::class);
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function checklistVersion(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SurveyAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    /** @return HasMany<SurveyTableRow, $this> */
    public function tableRows(): HasMany
    {
        return $this->hasMany(SurveyTableRow::class)->orderBy('checklist_table_block_id')->orderBy('position');
    }

    /** @return HasMany<SurveyEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(SurveyEvidence::class)->orderBy('category_key')->orderBy('id');
    }
}
