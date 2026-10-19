<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['survey_instance_id', 'checklist_version_id', 'checklist_table_block_id', 'position'])]
class SurveyTableRow extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SurveyTableRow $row): void {
            $row->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<SurveyInstance, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(SurveyInstance::class, 'survey_instance_id');
    }

    /** @return BelongsTo<ChecklistTableBlock, $this> */
    public function tableBlock(): BelongsTo
    {
        return $this->belongsTo(ChecklistTableBlock::class, 'checklist_table_block_id');
    }

    /** @return HasMany<SurveyTableCell, $this> */
    public function cells(): HasMany
    {
        return $this->hasMany(SurveyTableCell::class)->orderBy('checklist_table_column_id');
    }
}
