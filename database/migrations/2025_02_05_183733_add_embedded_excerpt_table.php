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
        Schema::create("embedded_excerpts", function (Blueprint $table) {
            $table->id();
            $table->vector("embedding", 2000);
            $table->string("hash", 32);
            $table->string("model");
            $table->string("reference");
            $table->integer("chapter")->nullable();
            $table->integer("verse")->nullable();
            $table->integer("to_chapter")->nullable();
            $table->integer("to_verse")->nullable();            
            $table->bigInteger("gepi")->nullable();            
            $table->string("translation_abbrev", 10);
            $table->string("usx_code", 3);
            $table->string("scope")->default("verse");
            $table->index(["reference", "translation_abbrev"]);
            $table->index("reference");
        });

        $prefix= Config::get('database.connections.bible.prefix');
        DB::statement("ALTER TABLE {$prefix}tdverse ALTER COLUMN numv TYPE INTEGER USING numv::integer");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("embedded_excerpts");
        Schema::table("tdverse", function (Blueprint $table) {
            $table->string('numv', 4)->change();
        });

    }
};
