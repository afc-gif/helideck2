<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['checklist_table_block_id', 'stable_key', 'label', 'source_label', 'data_type', 'unit', 'source_document_identifier', 'source_wording', 'source_locator', 'source_review_status', 'position'])]
class ChecklistTableColumn extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ChecklistTableColumn $column): void {
            $column->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['source_locator' => 'array'];
    }

    /** @return BelongsTo<ChecklistTableBlock, $this> */
    public function tableBlock(): BelongsTo
    {
        return $this->belongsTo(ChecklistTableBlock::class, 'checklist_table_block_id');
    }
}
