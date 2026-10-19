<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_definition_id', 'version_number', 'status', 'published_at', 'published_by'])]
class ChecklistVersion extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ChecklistDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ChecklistDefinition::class, 'checklist_definition_id');
    }

    /** @return HasMany<ChecklistSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class)->orderBy('position');
    }

    /** @return HasMany<SurveyInstance, $this> */
    public function surveyInstances(): HasMany
    {
        return $this->hasMany(SurveyInstance::class);
    }
}
