<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['checklist_section_id', 'stable_key', 'label', 'description', 'instructions', 'field_type', 'is_required', 'validation_rules', 'visibility_condition', 'comment_requirement', 'unit', 'position', 'is_sample'])]
class ChecklistQuestion extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ChecklistQuestion $question): void {
            $question->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_sample' => 'boolean',
            'validation_rules' => 'array',
            'visibility_condition' => 'array',
            'comment_requirement' => 'array',
        ];
    }

    /** @return BelongsTo<ChecklistSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    /** @return HasMany<AnswerOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AnswerOption::class)->orderBy('position');
    }

    /** @return HasMany<SurveyAnswer, $this> */
    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
