<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['survey_instance_id', 'uploaded_by', 'asset_id', 'public_id', 'category_key', 'category_label', 'source_document_identifier', 'source_table_number', 'original_filename', 'format', 'mime_type', 'byte_size', 'width', 'height', 'caption', 'cloudinary_version', 'uploaded_at'])]
class SurveyEvidence extends Model
{
    protected function casts(): array
    {
        return ['uploaded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SurveyInstance, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(SurveyInstance::class, 'survey_instance_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
