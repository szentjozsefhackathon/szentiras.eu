<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Models\VerseFix;

/**
 * @extends Factory<VerseFix>
 */
class VerseFixFactory extends Factory
{
    protected $model = VerseFix::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chapter = $this->faker->numberBetween(1, 50);
        $numv = $this->faker->numberBetween(1, 30);
        $originalText = $this->faker->sentence();

        return [
            'translation_id' => 1001,
            'usx_code' => 'GEN',
            'gepi' => "GEN_{$chapter}_{$numv}",
            'chapter' => $chapter,
            'numv' => $numv,
            'tip' => 6,
            'original_text' => $originalText,
            'fixed_text' => $originalText.' (javítva)',
            'note' => null,
            'status' => VerseFixStatus::Active,
        ];
    }

    public function withStatus(VerseFixStatus $status): static
    {
        return $this->state([
            'status' => $status,
        ]);
    }
}
