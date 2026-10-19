<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'survey_type_id', 'name', 'description'])]
class ChecklistDefinition extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ChecklistDefinition $definition): void {
            $definition->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<SurveyType, $this> */
    public function surveyType(): BelongsTo
    {
        return $this->belongsTo(SurveyType::class);
    }

    /** @return HasMany<ChecklistVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ChecklistVersion::class);
    }
}
