<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SzentirasHu\Models\Tag;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tag::query()->firstOrCreate(
            ['slug' => 'utmutato'],
            ['name' => 'Útmutató'],
        );
    }
}
