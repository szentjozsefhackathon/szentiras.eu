<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTranslationRUF2014 extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{

		DB::table('translations')->where('id', 2)->update(
				array(
					'name' => 'Magyar Bibliatársulat újfordítású Bibliája (1990)',
					'copyright' => 'Az 1990-es újfordítású Bibliát a <a href="http://bibliatarsulat.hu/">Magyar Bibliatársulat</a> ideiglenes engedélyével publikáljuk.'
					)
		);		
		DB::table('translations')->insert(
		    array(
        		'id'=>6,
		    	'created_at' => date('Y-m-d H:i:s'),
		    	'updated_at' => date('Y-m-d H:i:s'),
		    	'name' => 'Magyar Bibliatársulat újfordítású Bibliája (2014)', 
		    	'abbrev' => 'RUF',
		    	'order' => 9,
		    	'denom' => 'protestáns',
		    	'lang' => 'magyar',
		    	'copyright' => 'A 2014-es revidált Bibliát a <a href="http://bibliatarsulat.hu/">Magyar Bibliatársulat</a> ideiglenes engedélyével publikáljuk. A hivatalos változat <a href="http://abibliamindenkie.hu/">ott látható</a>.',
		    	'publisher' => 'Katolikus Bibliatársulat',
		    	'publisher_url' => 'http://bibliatarsulat.hu'
		    	)
		);

		$migrationsPath = base_path('database/migrations');
        $file = fopen("{$migrationsPath}/2015_10_11_020034_addTranslation_RUF2014.csv", "r");
        while ($data= fgetcsv($file)) {
            DB::table('books')->insert(
            	[
            		'number' => $data[0],
        			'created_at' => date('Y-m-d H:i:s'),
		    		'updated_at' => date('Y-m-d H:i:s'),
		    		'translation_id' => 6,
		    		'name' => $data[1],
		    		'abbrev' => $data[2],
		    		'link' => $data[3],
		    		'old_testament'=>$data[4],
            		]
            );
        }
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		DB::table('tdverse')->where('trans', 6)->delete();
		DB::table('books')->where('translation_id', 6)->delete();
        DB::table('translations')->where('abbrev', 'RUF')->delete();

        DB::table('translations')->where('id', 2)->update(
        	array(
        		'name' => 'Magyar Bibliatársulat újfordítású Bibliája',
        		'copyright' => '...'
        		)
        );		
	}

}
