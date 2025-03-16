<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CleanTdverse extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    { 
        Schema::table('tdverse', function(Blueprint $table)
        {
            $table->dropColumn('did');
            $table->dropColumn('hiv');
            $table->dropColumn('old');
            $table->dropColumn('jelenseg');
            $table->dropColumn('versesimple');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tdverse', function(Blueprint $table)
        {
            $table->string('versesimple')->nullable();
            $table->string('jelenseg')->nullable();
            $table->integer('old')->nullable();
            $table->string('hiv',15)->nullable();
            $table->integer('did')->nullable();  
        });
    }

}
