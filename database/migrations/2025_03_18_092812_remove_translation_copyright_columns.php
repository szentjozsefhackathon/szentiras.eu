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
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('copyright');
            $table->dropColumn('publisher');
            $table->dropColumn('publisher_url');
            $table->dropColumn('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->string('copyright')->nullable();
            $table->string('publisher')->nullable();
            $table->string('publisher_url')->nullable();
            $table->text('reference')->nullable();
        });
    }
};
