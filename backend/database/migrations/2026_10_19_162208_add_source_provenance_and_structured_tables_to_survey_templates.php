<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_sections', function (Blueprint $table): void {
            $table->string('source_document_identifier')->nullable();
            $table->text('source_wording')->nullable();
            $table->json('source_locator')->nullable();
            $table->string('source_review_status', 32)->default('unreviewed');
            $table->unique(['checklist_version_id', 'id'], 'checklist_sections_version_id_unique');
        });

        Schema::table('checklist_questions', function (Blueprint $table): void {
            $table->string('source_document_identifier')->nullable();
            $table->text('source_wording')->nullable();
            $table->json('source_locator')->nullable();
            $table->string('source_review_status', 32)->default('unreviewed');
        });

        Schema::table('survey_instances', function (Blueprint $table): void {
            $table->unique(['id', 'checklist_version_id'], 'survey_instances_id_version_unique');
        });

        Schema::create('checklist_table_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('checklist_section_id');
            $table->uuid('public_id')->unique();
            $table->string('stable_key');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['checklist_version_id', 'id'], 'checklist_table_blocks_version_id_unique');
            $table->unique(['checklist_section_id', 'position'], 'checklist_table_blocks_section_position_unique');
            $table->index('stable_key');
            $table->foreign(['checklist_version_id', 'checklist_section_id'], 'checklist_table_blocks_section_version_foreign')
                ->references(['checklist_version_id', 'id'])
                ->on('checklist_sections')
                ->cascadeOnDelete();
        });

        Schema::create('checklist_table_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_table_block_id')->constrained('checklist_table_blocks')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('stable_key');
            $table->string('label');
            $table->string('source_label')->nullable();
            $table->string('data_type', 32);
            $table->string('unit')->nullable();
            $table->string('source_document_identifier')->nullable();
            $table->text('source_wording')->nullable();
            $table->json('source_locator')->nullable();
            $table->string('source_review_status', 32)->default('unreviewed');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['checklist_table_block_id', 'id'], 'checklist_table_columns_block_id_unique');
            $table->unique(['checklist_table_block_id', 'position'], 'checklist_table_columns_block_position_unique');
            $table->index('stable_key');
        });

        Schema::create('survey_table_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_instance_id');
            $table->foreignId('checklist_version_id');
            $table->foreignId('checklist_table_block_id');
            $table->uuid('public_id')->unique();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['id', 'checklist_table_block_id'], 'survey_table_rows_id_block_unique');
            $table->unique(['survey_instance_id', 'checklist_table_block_id', 'position'], 'survey_table_rows_survey_block_position_unique');
            $table->foreign(['survey_instance_id', 'checklist_version_id'], 'survey_table_rows_survey_version_foreign')
                ->references(['id', 'checklist_version_id'])
                ->on('survey_instances')
                ->cascadeOnDelete();
            $table->foreign(['checklist_version_id', 'checklist_table_block_id'], 'survey_table_rows_block_version_foreign')
                ->references(['checklist_version_id', 'id'])
                ->on('checklist_table_blocks')
                ->restrictOnDelete();
        });

        Schema::create('survey_table_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_table_row_id');
            $table->foreignId('checklist_table_block_id');
            $table->foreignId('checklist_table_column_id');
            $table->json('value')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['survey_table_row_id', 'checklist_table_column_id'], 'survey_table_cells_row_column_unique');
            $table->foreign(['survey_table_row_id', 'checklist_table_block_id'], 'survey_table_cells_row_block_foreign')
                ->references(['id', 'checklist_table_block_id'])
                ->on('survey_table_rows')
                ->cascadeOnDelete();
            $table->foreign(['checklist_table_block_id', 'checklist_table_column_id'], 'survey_table_cells_column_block_foreign')
                ->references(['checklist_table_block_id', 'id'])
                ->on('checklist_table_columns')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_table_cells');
        Schema::dropIfExists('survey_table_rows');
        Schema::dropIfExists('checklist_table_columns');
        Schema::dropIfExists('checklist_table_blocks');

        Schema::table('survey_instances', function (Blueprint $table): void {
            $table->dropUnique('survey_instances_id_version_unique');
        });

        Schema::table('checklist_questions', function (Blueprint $table): void {
            $table->dropColumn(['source_document_identifier', 'source_wording', 'source_locator', 'source_review_status']);
        });

        Schema::table('checklist_sections', function (Blueprint $table): void {
            $table->dropUnique('checklist_sections_version_id_unique');
            $table->dropColumn(['source_document_identifier', 'source_wording', 'source_locator', 'source_review_status']);
        });
    }
};
