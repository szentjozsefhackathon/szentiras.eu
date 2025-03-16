<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrderToTranslations extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('translations', function($table)
		{
		    $table->integer('order')->after('abbrev')->nullable();
		});

        DB::table('translations')->where('id', 1)->update(array('order' => 3));
        DB::table('translations')->where('id', 3)->update(array('order' => 1));
        DB::table('translations')->where('id', 5)->update(array('order' => 5)); // Bekes-Dalos

        DB::table('translations')->where('id', 2)->update(array('order' => 10));
        DB::table('translations')->where('id', 4)->update(array('order' => 11));

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('translations', function(Blueprint $table)
        {
            $table->dropColumn('order');
        });
	}

}
