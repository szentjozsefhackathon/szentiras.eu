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
        Schema::create('greek_verses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('source', 10);
            $table->string('usx_code', 3);
            $table->unsignedInteger('chapter');
            $table->unsignedInteger('verse');
            $table->text('text')->collation("el-GR-x-icu");
            $table->text('json');
            $table->text('strongs')->collation("el-GR-x-icu");
            $table->text('strong_transliterations');
            $table->text('strong_normalizations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greek_verses');
    }
};
