<?php

namespace SzentirasHu\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\StrongWord;

class LookupGreekWordTool extends Tool
{
    protected string $name = 'lookup-greek-word';

    protected string $title = 'Look up a Greek word';

    protected string $description = 'Returns the Greek–Hungarian dictionary entry for a Strong number: the lemma, its transliteration, every Hungarian meaning with its explanation, and the etymology, paradigm and notes where known. Use it to follow up a Strong number returned by get-greek-verses instead of translating Greek from memory.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'strongNumber' => $schema->integer()
                ->description('Strong number of the Greek word, as returned in the `strongNumber` field of get-greek-verses, e.g. 26 for ἀγάπη.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'strongNumber' => ['required', 'integer', 'min:1'],
        ]);

        $strongWord = StrongWord::where('number', $validated['strongNumber'])->first();

        if ($strongWord === null) {
            return Response::error("No Greek word found for Strong number {$validated['strongNumber']}.");
        }

        $dictionaryEntry = $strongWord->dictionaryEntry;

        return Response::json([
            'strongNumber' => $strongWord->number,
            'lemma' => $strongWord->lemma,
            'transliteration' => $strongWord->transliteration,
            'meanings' => $this->meaningsOf($strongWord),
            'etymology' => $dictionaryEntry?->etymology,
            'paradigm' => $dictionaryEntry?->paradigm,
            'notes' => $dictionaryEntry?->notes,
            'source' => $dictionaryEntry?->source,
            'verseOccurrences' => $strongWord->greekVerses()->count(),
        ]);
    }

    /**
     * The Hungarian meanings of the word, most common first.
     *
     * @return array<int, array{meaning: string, explanation: ?string}>
     */
    private function meaningsOf(StrongWord $strongWord): array
    {
        return $strongWord->dictionaryMeanings()
            ->orderBy('order')
            ->get()
            ->map(fn (DictionaryMeaning $dictionaryMeaning): array => [
                'meaning' => $dictionaryMeaning->meaning,
                'explanation' => $dictionaryMeaning->explanation,
            ])
            ->all();
    }
}
