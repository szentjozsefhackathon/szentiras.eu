<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOptionalTranslationToBookAbbrevs extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::table('book_abbrevs', function(Blueprint $table)
        {
            $table->tinyInteger('translation_id', false, true)->nullable();
        });

        DB::table('book_abbrevs')
            ->where('abbrev', 'Jud')
            ->where('books_id', 118)
            ->update(['translation_id' => 1]);

        $prefix= Config::get('database.connections.bible.prefix');
        DB::statement("ALTER TABLE {$prefix}book_abbrevs ALTER COLUMN abbrev TYPE VARCHAR(255) COLLATE \"hu-x-icu\"");

    }

	public function down()
	{
        Schema::table('book_abbrevs', function(Blueprint $table)
        {
            $table->dropColumn('translation_id');
        });

    }

}
