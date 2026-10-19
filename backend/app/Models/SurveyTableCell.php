<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['survey_table_row_id', 'checklist_table_block_id', 'checklist_table_column_id', 'value', 'comment', 'updated_by'])]
class SurveyTableCell extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return BelongsTo<SurveyTableRow, $this> */
    public function row(): BelongsTo
    {
        return $this->belongsTo(SurveyTableRow::class, 'survey_table_row_id');
    }

    /** @return BelongsTo<ChecklistTableColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(ChecklistTableColumn::class, 'checklist_table_column_id');
    }
}
