<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Models\VerseFix;
use SzentirasHu\Models\VerseFixRevision;

/**
 * @extends Factory<VerseFixRevision>
 */
class VerseFixRevisionFactory extends Factory
{
    protected $model = VerseFixRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalText = $this->faker->sentence();

        return [
            'verse_fix_id' => VerseFix::factory(),
            'original_text' => $originalText,
            'fixed_text' => $originalText.' (javítva)',
            'note' => null,
            'status' => VerseFixStatus::Active,
            'source' => VerseFixRevision::SOURCE_EDITOR,
            'editor_token' => null,
        ];
    }
}
