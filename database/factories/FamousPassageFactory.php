<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Models\FamousPassage;

/**
 * Defaults to the seeded test text: TESTTRANS "Ter 2" (GEN 2, verses 3-4).
 *
 * @extends Factory<FamousPassage>
 */
class FamousPassageFactory extends Factory
{
    protected $model = FamousPassage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'a-hetedik-nap',
            'name' => 'A hetedik nap',
            'alternative_names' => ['Isten megpihen'],
            'reference' => 'Ter 2',
            'key_verse' => 'Ter 2,3',
            'usx_code' => 'GEN',
            'chapter_from' => 2,
            'chapter_to' => 2,
            'introduction' => 'A teremtés elbeszélésének zárása: Isten megáldja és megszenteli a hetedik napot.',
            'content' => "## Összefüggés\n\nA teremtéstörténet első elbeszélésének vége.",
            'seo_title' => null,
            'seo_description' => null,
            'is_active' => true,
            'position' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
