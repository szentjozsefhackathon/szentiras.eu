<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSynonymsTable extends Migration {

    private $data = [
        [ "Jézus", "Krisztus"],
        ["létra", "lajtorja"],
        ["lámpás", "mécses"],
        ["144000", "száznegyvennégyezer"],
        ["Melkizedek", "Melkisédek"],
        ["gyarapodott", "növekedett"],
        ["talentum", "tálentum"],
        ["csillagfejtő", "jós"],
        ["lama sabaktani", "lamma szabaktani", "lamá sabaktáni", "lemá szabaktáni"],
        ["juh", "bárány"],
        ["juhkapu", "Juh-kapu"],
        ["Beteszda","Betesdának","Betezdának","Bethesda", "Betezda", "Betesda"],
        ["nathanael","Nátánáel"],
        ["Kain", "Káin"],
        ["fölment", "felment"]
    ];

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('synonyms', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
            $table->string('word')->nullable();
            $table->integer('group')->nullable();
		});

        $this->addInitialData();
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('synonyms');
	}

    private function addInitialData() {
        $synonymRepository = \App::make('SzentirasHu\Data\Repository\SynonymRepository');
        foreach ($this->data as $synonyms) {
            $synonymRepository->addSynonyms($synonyms);
        }
    }

}
