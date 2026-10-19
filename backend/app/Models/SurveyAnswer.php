<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['survey_instance_id', 'checklist_question_id', 'value', 'comment', 'updated_by'])]
class SurveyAnswer extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return BelongsTo<SurveyInstance, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(SurveyInstance::class, 'survey_instance_id');
    }

    /** @return BelongsTo<ChecklistQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
