<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToTdverse extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('tdverse', function(Blueprint $table)
		{
			$table->index('book');
            $table->index('gepi');
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
            $table->dropIndex('tdverse_gepi_index');
        });
	}

}
