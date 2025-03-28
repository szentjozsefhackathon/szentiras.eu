<?php
// Source: https://github.com/gyuris/halld/
use Illuminate\Database\Migrations\Migration;


return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        $this->addInitialData();
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		DB::table('reading_plans')->where('id', 2)->delete();
        DB::table('reading_plans')->where('id', 3)->delete();
    }

    private function addInitialData() {
        $this->addReadingPlans();
        $this->addReadingPlanData();
    }

    private function addReadingPlans() {
        DB::table('reading_plans')->insert(
        	[
        		'id' => 2,
        		'name' => '365 napos logikus terv (teljes Szentírás)',
        		'description' => 'A teljes katolikus Biblia szövege logikusan sorba rendezve egy évre, napi 3–4 fejezet. https://halld.ujevangelizacio.hu, Gyuris Gellért',
            ]
        );
        DB::table('reading_plans')->insert(
        	[
        		'id' => 3,
        		'name' => '365 napos logikus terv (Újszövetség + Zsoltárok)',
        		'description' => 'Újszövetség és Zsoltárok könyve 1 év alatt kényelmesen. https://halld.ujevangelizacio.hu, Gyuris Gellért',
            ]
        );

    }

    private function addReadingPlanData() {
		$migrationsPath = base_path('database/migrations');
        $file = fopen("{$migrationsPath}/2025_03_27_151441_add_reading_plan_gyuris.csv", "r");
        while ($data = fgetcsv($file)) {
            DB::table('reading_plan_days')->insert(
            	[
            		'plan_id' => $data[0],
            		'day_number' => $data[1],
            		'verses' => $data[2],
					'description' => '',
            	]
            );
        }
    }

};
