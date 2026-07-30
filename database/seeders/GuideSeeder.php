<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SzentirasHu\Models\Guide;
use SzentirasHu\Models\Tag;

class GuideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guideTag = Tag::query()->firstOrCreate(
            ['slug' => 'utmutato'],
            ['name' => 'Útmutató'],
        );

        Guide::factory()
            ->count(3)
            ->create()
            ->each(function (Guide $guide) use ($guideTag): void {
                $guide->tags()->attach($guideTag);
            });
    }
}
