<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fix can also change the verse type of the record (e.g. hide a stray poem line).
     */
    public function up(): void
    {
        Schema::table('verse_fixes', function (Blueprint $table) {
            $table->integer('fixed_tip')->nullable()->after('fixed_text');
        });
        Schema::table('verse_fix_revisions', function (Blueprint $table) {
            $table->integer('fixed_tip')->nullable()->after('fixed_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verse_fix_revisions', function (Blueprint $table) {
            $table->dropColumn('fixed_tip');
        });
        Schema::table('verse_fixes', function (Blueprint $table) {
            $table->dropColumn('fixed_tip');
        });
    }
};
