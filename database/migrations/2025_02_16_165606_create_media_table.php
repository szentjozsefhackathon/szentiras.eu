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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->string('filename');
            $table->string('mime_type');
            $table->foreignId('media_type_id');
            $table->foreign('media_type_id')->references('id')->on('media_types');
            $table->string('usx_code')->nullable();
            $table->integer('chapter')->nullable();
            $table->integer('verse')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
