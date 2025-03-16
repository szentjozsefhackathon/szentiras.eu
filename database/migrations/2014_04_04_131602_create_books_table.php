<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBooksTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name', 100);
            $table->string('abbrev', 10);
            $table->string('denom', 20);
            $table->string('lang', 10);
            $table->longText('copyright')->nullable();
            $table->string('publisher', 200)->nullable();
            $table->string('publisher_url', 200)->nullable();
            $table->string('reference', 255)->nullable();

        });

        Schema::create('books', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('number')->unsigned();
            $table->timestamps();
            $table->unsignedInteger('translation_id')->foreign('translation_id')->references('id')->on('translations');
            $table->primary('id');
            $table->string('name', 100);
            $table->string('abbrev', 10);
            $table->string('link', 10);
            $table->integer('old_testament');
        });
	}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('books');
        Schema::drop('translations');
    }

}
