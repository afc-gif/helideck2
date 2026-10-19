<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['checklist_version_id', 'title', 'description', 'position', 'source_document_identifier', 'source_wording', 'source_locator', 'source_review_status'])]
class ChecklistSection extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ChecklistSection $section): void {
            $section->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['source_locator' => 'array'];
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return HasMany<ChecklistQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('position');
    }

    /** @return HasMany<ChecklistTableBlock, $this> */
    public function tableBlocks(): HasMany
    {
        return $this->hasMany(ChecklistTableBlock::class)->orderBy('position');
    }
}
