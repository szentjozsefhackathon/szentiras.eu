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
        Schema::create('famous_passages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->jsonb('alternative_names');
            $table->string('reference', 100);
            $table->string('key_verse', 100)->nullable();
            $table->string('usx_code', 3);
            $table->unsignedSmallInteger('chapter_from');
            $table->unsignedSmallInteger('chapter_to');
            $table->text('introduction')->nullable();
            $table->text('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['usx_code', 'chapter_from', 'chapter_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('famous_passages');
    }
};
