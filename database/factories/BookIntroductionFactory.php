<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Models\BookIntroduction;

/**
 * @extends Factory<BookIntroduction>
 */
class BookIntroductionFactory extends Factory
{
    protected $model = BookIntroduction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'translation_id' => 1001,
            'usx_codes' => ['GEN'],
            'is_group' => false,
            'title' => 'Bevezetés a Teremtés könyvéhez',
            'paragraphs' => [
                ['style' => 'paragraph', 'segments' => [['text' => $this->faker->paragraph()]]],
            ],
            'position' => $this->faker->unique()->numberBetween(1, 10000),
        ];
    }

    /**
     * An introduction printed before a group of books.
     *
     * @param  list<string>  $usxCodes
     */
    public function group(array $usxCodes): static
    {
        return $this->state([
            'usx_codes' => $usxCodes,
            'is_group' => true,
            'title' => 'Bevezetés a Törvény öt könyvéhez',
        ]);
    }
}
