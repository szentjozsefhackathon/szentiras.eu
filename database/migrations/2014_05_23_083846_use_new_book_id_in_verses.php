<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UseNewBookIdInVerses extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('tdverse', function(Blueprint $table)
		{
            $table->unsignedInteger('book_id');
		});
        foreach (DB::table('books')->get() as $book) {
            DB::table('tdverse')
                ->where('book', $book->number)
                ->where('trans', $book->translation_id)
                ->update(['book_id' => $book->id]);
        }
        Schema::table('tdverse', function(Blueprint $table)
        {
            $table->foreign('book_id')->references('id')->on('books');
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
            $table->dropColumn('book_id');
        });
    }

}
