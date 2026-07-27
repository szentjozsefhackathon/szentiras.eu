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
        Schema::create('greek_verse_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('gepi', 20);
            $table->string('greek_source', 20);
            $table->string('locale', 10)->default('hu');
            $table->unsignedSmallInteger('format_version');
            $table->jsonb('analysis');
            $table->string('generated_by');
            $table->date('generated_at');
            $table->string('source_key');
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(
                ['greek_source', 'locale', 'gepi'],
                'greek_verse_analyses_source_locale_gepi_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greek_verse_analyses');
    }
};
