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
        Schema::create('greek_verse_strong_word', function (Blueprint $table) {
            $table->id();
            $table->foreignId('greek_verse_id');
            $table->foreignId('strong_word_id');
            $table->integer('position');
            $table->foreign('greek_verse_id')->references('id')->on('greek_verses');
            $table->foreign('strong_word_id')->references('id')->on('strong_words');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greek_verse_strong_word');
    }
};
