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
        Schema::create('book_introductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('translation_id')->index();
            $table->jsonb('usx_codes');
            $table->boolean('is_group')->default(false);
            $table->string('title')->nullable();
            $table->jsonb('paragraphs');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_introductions');
    }
};
