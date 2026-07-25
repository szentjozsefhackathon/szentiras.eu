<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SzentirasHu\Models\Guide;

class GuideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Guide::factory()->count(3)->create();
    }
}
