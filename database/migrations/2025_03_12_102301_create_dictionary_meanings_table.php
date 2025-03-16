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
        Schema::create('dictionary_meanings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('strong_word_number');
            $table->tinyInteger('order');
            $table->text('meaning');
            $table->text('explanation');
            $table->string('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dictionary_meanings');
    }
};
