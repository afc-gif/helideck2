<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'name', 'slug', 'description', 'is_active'])]
class SurveyType extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SurveyType $surveyType): void {
            $surveyType->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ChecklistDefinition, $this> */
    public function checklists(): HasMany
    {
        return $this->hasMany(ChecklistDefinition::class);
    }
}
