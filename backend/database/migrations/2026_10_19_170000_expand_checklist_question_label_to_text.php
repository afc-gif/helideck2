<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_questions', function (Blueprint $table): void {
            $table->text('label')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('checklist_questions')->whereRaw('CHAR_LENGTH(label) > 255')->exists()) {
            throw new RuntimeException('Cannot shrink checklist_questions.label: one or more labels exceed 255 characters.');
        }

        Schema::table('checklist_questions', function (Blueprint $table): void {
            $table->string('label', 255)->change();
        });
    }
};
