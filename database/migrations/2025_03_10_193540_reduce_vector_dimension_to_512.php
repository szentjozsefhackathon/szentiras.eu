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
        DB::table('embedded_excerpts')->truncate();
        Schema::table('embedded_excerpts', function (Blueprint $table) {            
            $table->dropColumn('embedding');
            $table->vector('embedding', 512);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('embedded_excerpts')->truncate();
        Schema::table('embedded_excerpts', function (Blueprint $table) {
            $table->dropColumn('embedding');
            $table->vector('embedding', 2000);
        });
    }
};
