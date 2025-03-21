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
        Schema::table('greek_verses', function (Blueprint $table) {
            $table->text('transliteration')->nullable();
            $table->text('normalization')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('greek_verses', function (Blueprint $table) {
            $table->dropColumn('transliteration');
            $table->dropColumn('normalization');
        });
    }
};
