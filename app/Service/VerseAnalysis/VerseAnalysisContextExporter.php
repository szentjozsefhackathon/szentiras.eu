<?php

namespace SzentirasHu\Service\VerseAnalysis;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Mcp\GreekReferenceResolver;
use SzentirasHu\Mcp\TranslationResolver;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\MorphologyService;
use SzentirasHu\Service\Text\TextService;

class VerseAnalysisContextExporter
{
    public function __construct(
        private readonly GreekReferenceResolver $greekReferenceResolver,
        private readonly TranslationResolver $translationResolver,
        private readonly TextService $textService,
        private readonly MorphologyService $morphologyService,
        private readonly VerseAnalysisSemanticValidator $semanticValidator,
    ) {}

    /**
     * @param  array<int, string>  $translationAbbreviations
     * @return array<string, mixed>
     */
    public function export(
        string $reference,
        string $rootDirectory,
        int $chunkWordLimit,
        array $translationAbbreviations,
    ): array {
        if ($chunkWordLimit < 1) {
            throw new InvalidArgumentException('The chunk word limit must be at least 1.');
        }

        if ($translationAbbreviations === []) {
            throw new InvalidArgumentException('At least one Hungarian translation is required.');
        }

        $canonicalReference = $this->canonicalReference($reference);
        $greekVerses = $this->greekReferenceResolver->versesFor($canonicalReference)->values();

        if ($greekVerses->isEmpty()) {
            throw new InvalidArgumentException("No Greek verses found for '{$reference}'.");
        }

        $chapters = $greekVerses->groupBy(
            fn (GreekVerse $verse): string => "{$verse->usx_code}_{$verse->chapter}"
        );

        if ($chapters->count() !== 1) {
            throw new InvalidArgumentException('The reference must identify exactly one New Testament chapter.');
        }

        $firstVerse = $greekVerses->firstOrFail();
        $book = $this->greekReferenceResolver->bookAbbrevFor($firstVerse->usx_code) ?? $firstVerse->usx_code;
        $chapterKey = "{$firstVerse->usx_code}_{$firstVerse->chapter}";
        $directory = rtrim($rootDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$chapterKey;
        $this->ensureDirectoryExists($directory);

        $translations = $this->translationTexts(
            $canonicalReference,
            $translationAbbreviations,
            $greekVerses,
        );
        $analysedWords = $greekVerses->mapWithKeys(
            fn (GreekVerse $verse): array => [$verse->verse => $this->analysedWords($verse)]
        )->all();
        $chunks = $this->chunks($greekVerses, $analysedWords, $chunkWordLimit);
        $previousManifest = $this->readOptionalJson($directory.'/manifest.json');
        $previousChunks = collect($previousManifest['chunks'] ?? [])->keyBy('id');
        $manifestChunks = [];

        foreach ($chunks as $chunkIndex => $chunkVerses) {
            $chunkId = str_pad((string) ($chunkIndex + 1), 3, '0', STR_PAD_LEFT);
            $sourceFile = "source-{$chunkId}.json";
            $semanticFile = "semantic-{$chunkId}.json";
            $source = $this->chunkSource(
                $chunkVerses,
                $greekVerses,
                $analysedWords,
                $translations,
                $book,
                $translationAbbreviations,
            );
            $sourceHash = hash('sha256', $this->encode($source));
            $semanticPath = $directory.DIRECTORY_SEPARATOR.$semanticFile;
            $previousChunk = $previousChunks->get($chunkId);

            if (
                is_array($previousChunk)
                && ($previousChunk['sourceHash'] ?? null) === $sourceHash
                && is_file($semanticPath)
            ) {
                $semantic = $this->readOptionalJson($semanticPath);

                try {
                    $this->semanticValidator->validate($source, $semantic);
                } catch (\UnexpectedValueException) {
                    unlink($semanticPath);
                }
            } elseif (is_file($semanticPath)) {
                unlink($semanticPath);
            }

            $this->writeJson($directory.DIRECTORY_SEPARATOR.$sourceFile, $source);

            $manifestChunks[] = [
                'id' => $chunkId,
                'reference' => $source['reference'],
                'source' => $sourceFile,
                'semantic' => $semanticFile,
                'sourceHash' => $sourceHash,
                'verses' => $chunkVerses->pluck('verse')->all(),
                'wordCount' => $chunkVerses->sum(
                    fn (GreekVerse $verse): int => count($analysedWords[$verse->verse] ?? [])
                ),
            ];
        }

        $manifest = [
            'format' => 1,
            'reference' => "{$book} {$firstVerse->chapter}",
            'chapterKey' => $chapterKey,
            'usxCode' => $firstVerse->usx_code,
            'book' => $book,
            'chapter' => $firstVerse->chapter,
            'greekSource' => $firstVerse->source,
            'translations' => array_values($translationAbbreviations),
            'chunkWordLimit' => $chunkWordLimit,
            'chunks' => $manifestChunks,
        ];

        $this->writeJson($directory.'/manifest.json', $manifest);

        return [
            ...$manifest,
            'directory' => $directory,
            'manifestPath' => $directory.'/manifest.json',
        ];
    }

    private function canonicalReference(string $reference): CanonicalReference
    {
        try {
            return CanonicalReference::fromString($reference);
        } catch (ParsingException $exception) {
            throw new InvalidArgumentException(
                "Could not parse '{$reference}' as a Bible reference.",
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<int, string>  $translationAbbreviations
     * @param  Collection<int, GreekVerse>  $greekVerses
     * @return array<int, array<string, string>>
     */
    private function translationTexts(
        CanonicalReference $reference,
        array $translationAbbreviations,
        Collection $greekVerses,
    ): array {
        $translations = [];

        foreach ($translationAbbreviations as $abbreviation) {
            $translation = $this->translationResolver->resolve($abbreviation);

            foreach ($this->versesForTranslation($reference, $translation) as $verseNumber => $text) {
                $translations[$verseNumber][$abbreviation] = $text;
            }
        }

        foreach ($greekVerses as $verse) {
            foreach ($translationAbbreviations as $abbreviation) {
                if (! isset($translations[$verse->verse][$abbreviation])) {
                    throw new RuntimeException(
                        "Verse {$verse->chapter},{$verse->verse} is missing from translation {$abbreviation}."
                    );
                }
            }
        }

        return $translations;
    }

    /**
     * @return array<int, string>
     */
    private function versesForTranslation(
        CanonicalReference $reference,
        Translation $translation,
    ): array {
        $verses = [];

        foreach ($this->textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $verse) {
                $verses[(int) $verse->numv] = $verse->getText();
            }
        }

        return $verses;
    }

    /**
     * @param  Collection<int, GreekVerse>  $greekVerses
     * @param  array<int, array<int, array{printed: string, strongNumber: ?int, morphology: ?string}>>  $analysedWords
     * @return array<int, Collection<int, GreekVerse>>
     */
    private function chunks(
        Collection $greekVerses,
        array $analysedWords,
        int $chunkWordLimit,
    ): array {
        $chunks = [];
        $current = collect();
        $currentWordCount = 0;

        foreach ($greekVerses as $verse) {
            $wordCount = count($analysedWords[$verse->verse] ?? []);

            if ($current->isNotEmpty() && $currentWordCount + $wordCount > $chunkWordLimit) {
                $chunks[] = $current;
                $current = collect();
                $currentWordCount = 0;
            }

            $current->push($verse);
            $currentWordCount += $wordCount;
        }

        if ($current->isNotEmpty()) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  Collection<int, GreekVerse>  $chunkVerses
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @param  array<int, array<int, array{printed: string, strongNumber: ?int, morphology: ?string}>>  $analysedWords
     * @param  array<int, array<string, string>>  $translations
     * @param  array<int, string>  $translationAbbreviations
     * @return array<string, mixed>
     */
    private function chunkSource(
        Collection $chunkVerses,
        Collection $chapterVerses,
        array $analysedWords,
        array $translations,
        string $book,
        array $translationAbbreviations,
    ): array {
        $first = $chunkVerses->firstOrFail();
        $last = $chunkVerses->last();
        $strongNumbers = $chunkVerses
            ->flatMap(fn (GreekVerse $verse): array => $analysedWords[$verse->verse] ?? [])
            ->pluck('strongNumber')
            ->filter(fn (?int $strongNumber): bool => $strongNumber !== null)
            ->unique()
            ->values()
            ->all();
        $morphologyCodes = $chunkVerses
            ->flatMap(fn (GreekVerse $verse): array => $analysedWords[$verse->verse] ?? [])
            ->pluck('morphology')
            ->filter(fn (?string $morphology): bool => filled($morphology))
            ->unique()
            ->values();
        $range = $first->verse === $last->verse
            ? (string) $first->verse
            : "{$first->verse}-{$last->verse}";
        $firstPosition = $chapterVerses->search(
            fn (GreekVerse $verse): bool => $verse->verse === $first->verse
        );
        $lastPosition = $chapterVerses->search(
            fn (GreekVerse $verse): bool => $verse->verse === $last->verse
        );

        return [
            'format' => 1,
            'reference' => "{$book} {$first->chapter},{$range}",
            'wordTuple' => ['printed', 'strongNumber', 'morphology'],
            'translations' => array_values($translationAbbreviations),
            'dictionary' => $this->dictionary($strongNumbers),
            'morphology' => $morphologyCodes
                ->mapWithKeys(fn (string $code): array => [$code => $this->morphologyService->describe($code)])
                ->all(),
            'contextBefore' => is_int($firstPosition) && $firstPosition > 0
                ? $this->contextVerse($chapterVerses->get($firstPosition - 1), $translations)
                : null,
            'verses' => $chunkVerses
                ->map(fn (GreekVerse $verse): array => [
                    'verse' => $verse->verse,
                    'words' => array_map(
                        fn (array $word): array => [
                            $word['printed'],
                            $word['strongNumber'],
                            $word['morphology'],
                        ],
                        $analysedWords[$verse->verse] ?? [],
                    ),
                    'translations' => $translations[$verse->verse],
                ])
                ->values()
                ->all(),
            'contextAfter' => is_int($lastPosition) && $lastPosition < $chapterVerses->count() - 1
                ? $this->contextVerse($chapterVerses->get($lastPosition + 1), $translations)
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $strongNumbers
     * @return array<int, array<int, string>>
     */
    private function dictionary(array $strongNumbers): array
    {
        if ($strongNumbers === []) {
            return [];
        }

        return DictionaryMeaning::query()
            ->whereIn('strong_word_number', $strongNumbers)
            ->orderBy('strong_word_number')
            ->orderBy('order')
            ->get()
            ->groupBy('strong_word_number')
            ->map(
                fn (Collection $meanings): array => $meanings
                    ->pluck('meaning')
                    ->unique()
                    ->values()
                    ->all()
            )
            ->all();
    }

    /**
     * @param  array<int, array<string, string>>  $translations
     * @return array<string, mixed>|null
     */
    private function contextVerse(?GreekVerse $verse, array $translations): ?array
    {
        if ($verse === null) {
            return null;
        }

        return [
            'verse' => $verse->verse,
            'greek' => str_replace('¶', '', $verse->text),
            'translations' => $translations[$verse->verse] ?? [],
        ];
    }

    /**
     * @return array<int, array{printed: string, strongNumber: ?int, morphology: ?string}>
     */
    private function analysedWords(GreekVerse $verse): array
    {
        $storedAnalysis = json_decode((string) $verse->json);
        $storedAnalysis = is_array($storedAnalysis) ? $storedAnalysis : [];

        return collect($verse->annotatedWords())
            ->map(function (array $word, int $index) use ($storedAnalysis): array {
                $analysis = $storedAnalysis[$index] ?? null;
                $strongNumber = isset($analysis->strong) && is_numeric($analysis->strong)
                    ? (int) $analysis->strong
                    : null;

                return [
                    'printed' => $word['printed'],
                    'strongNumber' => $strongNumber,
                    'morphology' => isset($analysis->morphology)
                        ? (string) $analysis->morphology
                        : null,
                ];
            })
            ->all();
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create directory '{$directory}'.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readOptionalJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function writeJson(string $path, array $value): void
    {
        $directory = dirname($path);
        $temporaryPath = tempnam($directory, '.verse-analysis-');

        if ($temporaryPath === false) {
            throw new RuntimeException("Could not create a temporary file in '{$directory}'.");
        }

        $json = $this->encode($value);

        if (file_put_contents($temporaryPath, $json) === false || ! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Could not write '{$path}'.");
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
