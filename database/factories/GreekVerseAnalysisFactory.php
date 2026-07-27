<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Models\GreekVerseAnalysis;

/**
 * @extends Factory<GreekVerseAnalysis>
 */
class GreekVerseAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $verse = fake()->unique()->numberBetween(1, 999);
        $gepi = "MAT_1_{$verse}";
        $analysis = [
            'verse' => $verse,
            'gepi' => $gepi,
            'greekText' => 'λόγος',
            'segments' => [
                [
                    'wordIndexes' => [0],
                    'greek' => 'λόγος',
                    'meaning' => 'ige',
                ],
            ],
        ];

        return [
            'gepi' => $gepi,
            'greek_source' => 'OpenGNT',
            'locale' => GreekVerseAnalysis::DEFAULT_LOCALE,
            'format_version' => 1,
            'analysis' => $analysis,
            'generated_by' => 'test-factory',
            'generated_at' => '2026-07-27',
            'source_key' => 'greek/verse-analysis/OpenGNT/hu/v1/MAT_1.json',
            'content_hash' => hash(
                'sha256',
                json_encode($analysis, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ),
        ];
    }
}
