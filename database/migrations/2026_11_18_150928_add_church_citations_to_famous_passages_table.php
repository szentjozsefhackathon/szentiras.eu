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
        Schema::table('famous_passages', function (Blueprint $table) {
            $table->jsonb('church_citations')->nullable()->after('content');
            $table->timestamp('church_citations_fetched_at')->nullable()->after('church_citations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('famous_passages', function (Blueprint $table) {
            $table->dropColumn(['church_citations', 'church_citations_fetched_at']);
        });
    }
};
