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
        Schema::create('greek_verse_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string("source", 10);            
            $table->string("gepi", 20);            
            $table->string("usx_code", 3);
            $table->integer("chapter");
            $table->integer("verse");
            $table->string("model");
            $table->vector("embedding", 512);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greek_verse_embeddings');
    }
};
