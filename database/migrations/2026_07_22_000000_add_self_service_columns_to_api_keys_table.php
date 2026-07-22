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
        Schema::table('api_keys', function (Blueprint $table) {
            $table->boolean('is_self_service')->default(false)->after('is_internal');
            $table->string('key_plain')->nullable()->after('key_hash');

            $table->index('is_self_service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['is_self_service']);

            $table->dropColumn([
                'is_self_service',
                'key_plain',
            ]);
        });
    }
};
