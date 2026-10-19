<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['checklist_version_id', 'checklist_section_id', 'stable_key', 'title', 'description', 'position'])]
class ChecklistTableBlock extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ChecklistTableBlock $block): void {
            $block->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<ChecklistSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return HasMany<ChecklistTableColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(ChecklistTableColumn::class)->orderBy('position');
    }

    /** @return HasMany<SurveyTableRow, $this> */
    public function surveyRows(): HasMany
    {
        return $this->hasMany(SurveyTableRow::class)->orderBy('position');
    }
}
