<?php

namespace SzentirasHu\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SzentirasHu\Mcp\GreekReferenceResolver;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\MorphologyService;

class GetGreekVersesTool extends Tool
{
    protected string $name = 'get-greek-verses';

    protected string $title = 'Get Greek New Testament verses';

    protected string $description = 'Returns the original Greek text of a New Testament passage, word by word: the printed form, its dictionary form (lemma), transliteration, Strong number, morphological code with a Hungarian description, and the word\'s first Hungarian meaning. Use this instead of quoting or parsing Greek from memory. Only the New Testament exists in Greek. References use Hungarian notation: a comma separates chapter and verse (Jn 3,16), a hyphen marks a range (1Kor 13,4-7), and a semicolon separates books or chapters (Jn 1;3).';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('New Testament reference in Hungarian notation, e.g. "Jn 3,16" or "1Kor 13,4-7".')
                ->required(),
        ];
    }

    public function handle(Request $request, GreekReferenceResolver $resolver, MorphologyService $morphologyService): Response
    {
        $validated = $request->validate([
            'reference' => ['required', 'string'],
        ]);

        try {
            $canonicalReference = CanonicalReference::fromString($validated['reference']);
        } catch (ParsingException $exception) {
            return Response::error("Could not parse the reference '{$validated['reference']}'. Use Hungarian notation, for example 'Jn 3,16', '1Kor 13,4-7' or 'Jn 1;3'.");
        }

        $verses = $resolver->versesFor($canonicalReference);

        if ($verses->isEmpty()) {
            return Response::error("No Greek text found for '{$canonicalReference->toString()}'. Only the New Testament exists in Greek, so check that the reference names a New Testament book.");
        }

        $firstMeanings = $this->firstMeanings($verses);

        return Response::json([
            'reference' => $canonicalReference->toString(),
            'text' => $this->describeSource($verses),
            'verses' => $verses->map(fn (GreekVerse $verse): array => [
                'reference' => $this->referenceFor($verse, $resolver),
                'gepi' => $verse->gepi,
                'usxCode' => $verse->usx_code,
                'chapter' => $verse->chapter,
                'verse' => $verse->verse,
                'greekText' => str_replace('¶', '', $verse->text),
                'words' => $this->wordsFor($verse, $firstMeanings, $morphologyService),
            ])->all(),
        ]);
    }

    /**
     * The word-level analysis of a verse.
     *
     * The printed forms, lemmas and transliterations are aligned by position by the model,
     * while the Strong numbers and morphological codes come from the verse's stored
     * per-word analysis, which may be absent for older imports.
     *
     * @param  array<int, string>  $firstMeanings  First Hungarian meaning keyed by Strong number.
     * @return array<int, array<string, mixed>>
     */
    private function wordsFor(GreekVerse $verse, array $firstMeanings, MorphologyService $morphologyService): array
    {
        $analysedWords = $this->analysedWords($verse);

        return collect($verse->annotatedWords())
            ->map(function (array $word, int $i) use ($analysedWords, $firstMeanings, $morphologyService): array {
                $strongNumber = $this->strongNumberOf($analysedWords[$i] ?? null);
                $morphCode = $analysedWords[$i]->morphology ?? null;

                return [
                    'i' => $i,
                    'printed' => $word['printed'],
                    'lemma' => $word['strong'],
                    'transliteration' => $word['translit'],
                    'strongNumber' => $strongNumber,
                    'morphology' => $morphCode,
                    'morphologyDescription' => $morphologyService->describe($morphCode),
                    'meaning' => $strongNumber === null ? null : ($firstMeanings[$strongNumber] ?? null),
                ];
            })
            ->all();
    }

    /**
     * The stored per-word analysis of a verse, indexed the same way as its printed words.
     *
     * @return array<int, \stdClass>
     */
    private function analysedWords(GreekVerse $verse): array
    {
        $decoded = json_decode((string) $verse->json);

        return is_array($decoded) ? $decoded : [];
    }

    private function strongNumberOf(?\stdClass $analysedWord): ?int
    {
        return isset($analysedWord->strong) && is_numeric($analysedWord->strong)
            ? (int) $analysedWord->strong
            : null;
    }

    /**
     * The first Hungarian meaning of every Strong number occurring in the verses, fetched in
     * one query so that a long passage does not trigger a lookup per word.
     *
     * @param  Collection<int, GreekVerse>  $verses
     * @return array<int, string>
     */
    private function firstMeanings(Collection $verses): array
    {
        $strongNumbers = $verses
            ->flatMap(fn (GreekVerse $verse): array => $this->analysedWords($verse))
            ->map(fn (\stdClass $analysedWord): ?int => $this->strongNumberOf($analysedWord))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($strongNumbers === []) {
            return [];
        }

        $firstMeanings = [];

        foreach (DictionaryMeaning::whereIn('strong_word_number', $strongNumbers)->orderBy('order')->get() as $meaning) {
            $firstMeanings[$meaning->strong_word_number] ??= $meaning->meaning;
        }

        return $firstMeanings;
    }

    private function referenceFor(GreekVerse $verse, GreekReferenceResolver $resolver): string
    {
        $abbrev = $resolver->bookAbbrevFor($verse->usx_code) ?? $verse->usx_code;

        return "{$abbrev} {$verse->chapter},{$verse->verse}";
    }

    /**
     * @param  Collection<int, GreekVerse>  $verses
     * @return array<string, mixed>
     */
    private function describeSource(Collection $verses): array
    {
        $definition = config('translations.definitions.GNT', []);

        return [
            'abbrev' => 'GNT',
            'language' => 'grc',
            'edition' => $verses->first()->source,
            'copyright' => $definition['copyright'] ?? null,
            'publisher' => $definition['publisher'] ?? null,
        ];
    }
}
