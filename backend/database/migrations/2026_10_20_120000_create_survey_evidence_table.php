<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset_id')->unique();
            $table->string('public_id')->unique();
            $table->string('category_key', 80);
            $table->string('category_label');
            $table->string('source_document_identifier');
            $table->unsignedSmallInteger('source_table_number');
            $table->string('original_filename');
            $table->string('format', 12);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedBigInteger('cloudinary_version');
            $table->timestampTz('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->index(['survey_instance_id', 'category_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_evidence');
    }
};
