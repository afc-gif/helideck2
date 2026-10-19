<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('survey_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('checklist_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('survey_type_id');
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'survey_type_id']);
            $table->foreign(['organization_id', 'survey_type_id'])
                ->references(['organization_id', 'id'])
                ->on('survey_types')
                ->cascadeOnDelete();
        });

        Schema::create('checklist_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['checklist_definition_id', 'version_number']);
            $table->index(['checklist_definition_id', 'status']);
        });

        Schema::create('checklist_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_version_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['checklist_version_id', 'position']);
        });

        Schema::create('checklist_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('stable_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('field_type');
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('visibility_condition')->nullable();
            $table->json('comment_requirement')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('position');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->unique(['checklist_section_id', 'position']);
            $table->index('stable_key');
        });

        Schema::create('answer_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_question_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['checklist_question_id', 'value']);
            $table->unique(['checklist_question_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answer_options');
        Schema::dropIfExists('checklist_questions');
        Schema::dropIfExists('checklist_sections');
        Schema::dropIfExists('checklist_versions');
        Schema::dropIfExists('checklist_definitions');
        Schema::dropIfExists('survey_types');
    }
};
