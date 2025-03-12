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
        Schema::create('strong_words', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');            
            $table->unique('number');
            $table->string('lemma')->collation("el-GR-x-icu");
            $table->string('transliteration');            
            $table->string('normalized');
            $table->timestamps();            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strong_words');
    }
};
