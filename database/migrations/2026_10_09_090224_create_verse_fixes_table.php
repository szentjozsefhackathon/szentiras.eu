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
        Schema::create('verse_fixes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('translation_id');
            $table->string('usx_code', 10);
            $table->string('gepi', 64);
            $table->unsignedInteger('chapter');
            $table->unsignedInteger('numv');
            $table->integer('tip');
            $table->text('original_text');
            $table->text('fixed_text');
            $table->text('note')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('last_applied_at')->nullable();
            $table->timestamps();

            $table->index(['translation_id', 'gepi', 'tip']);
        });

        Schema::create('verse_fix_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_fix_id')->constrained('verse_fixes')->cascadeOnDelete();
            $table->text('original_text');
            $table->text('fixed_text');
            $table->text('note')->nullable();
            $table->string('status', 32);
            $table->string('source', 16);
            $table->string('editor_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verse_fix_revisions');
        Schema::dropIfExists('verse_fixes');
    }
};
