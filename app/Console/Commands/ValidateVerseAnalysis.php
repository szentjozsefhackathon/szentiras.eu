<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SzentirasHu\Mcp\GreekReferenceResolver;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;

/**
 * Validates the hand-curated, per chapter Greek verse analysis files against the Greek text
 * stored in the database.
 *
 * The files are produced chapter by chapter by agents (see the `gorog-elemzes` skill), so this
 * command is both their feedback loop and the guarantee that the files can later be imported:
 * every verse of the chapter has to be present, the Greek text has to match the database
 * letter by letter, and the segments have to partition the word indexes of the verse exactly.
 */
class ValidateVerseAnalysis extends Command
{
    /**
     * The only file format version this command understands.
     */
    private const FORMAT_VERSION = 1;

    protected $signature = 'szentiras:validate-verse-analysis
        {reference? : New Testament reference in Hungarian notation, e.g. "Jn" or "Jn 3". Defaults to the whole New Testament.}
        {--dir=bible_import/verse-analysis : Directory holding the per chapter analysis files, relative to the project root.}
        {--missing : List only the chapters that have no analysis file yet.}
        {--json : Output the result as JSON instead of one line per chapter.}';

    protected $description = 'Validate the per chapter Greek verse analysis JSON files against the stored Greek text.';

    public function __construct(private readonly GreekReferenceResolver $referenceResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $verses = $this->versesToCheck();

        if ($verses === null) {
            return self::FAILURE;
        }

        if ($verses->isEmpty()) {
            $this->error('No Greek verses found for the reference. Only the New Testament exists in Greek.');

            return self::FAILURE;
        }

        $results = $this->chaptersOf($verses)
            ->map(fn (Collection $chapterVerses): array => $this->validateChapter($chapterVerses))
            ->values();

        if ($this->option('missing')) {
            return $this->reportMissing($results);
        }

        return $this->report($results);
    }

    /**
     * The Greek verses the reference covers, or every Greek verse when no reference is given.
     *
     * @return Collection<int, GreekVerse>|null Null when the reference could not be parsed.
     */
    private function versesToCheck(): ?Collection
    {
        $reference = $this->argument('reference');

        if ($reference === null) {
            return GreekVerse::query()
                ->orderBy('usx_code')
                ->orderBy('chapter')
                ->orderBy('verse')
                ->get();
        }

        try {
            $canonicalReference = CanonicalReference::fromString($reference);
        } catch (ParsingException) {
            $this->error("Could not parse the reference '{$reference}'. Use Hungarian notation, for example 'Jn', 'Jn 3' or 'Jn 1;3'.");

            return null;
        }

        return $this->referenceResolver->versesFor($canonicalReference);
    }

    /**
     * The verses grouped into chapters, keyed by `USX_chapter`, in canonical order.
     *
     * @param  Collection<int, GreekVerse>  $verses
     * @return Collection<string, Collection<int, GreekVerse>>
     */
    private function chaptersOf(Collection $verses): Collection
    {
        return $verses
            ->sortBy([['usx_code', 'asc'], ['chapter', 'asc'], ['verse', 'asc']])
            ->groupBy(fn (GreekVerse $verse): string => "{$verse->usx_code}_{$verse->chapter}");
    }

    /**
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @return array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    private function validateChapter(Collection $chapterVerses): array
    {
        $first = $chapterVerses->first();
        $chapterKey = "{$first->usx_code}_{$first->chapter}";
        $path = $this->pathFor($chapterKey);

        if (!is_file($path)) {
            return [
                'chapter' => $chapterKey,
                'path' => $path,
                'exists' => false,
                'errors' => ['The analysis file does not exist.'],
                'warnings' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return [
                'chapter' => $chapterKey,
                'path' => $path,
                'exists' => true,
                'errors' => ['The file is not valid JSON: '.json_last_error_msg().'.'],
                'warnings' => [],
            ];
        }

        $errors = $this->headerErrors($decoded, $first);
        $warnings = [];

        [$verseErrors, $verseWarnings] = $this->verseErrors($decoded, $chapterVerses);

        return [
            'chapter' => $chapterKey,
            'path' => $path,
            'exists' => true,
            'errors' => array_merge($errors, $verseErrors),
            'warnings' => array_merge($warnings, $verseWarnings),
        ];
    }

    /**
     * Errors of the chapter level fields, which have to agree with the file name.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<int, string>
     */
    private function headerErrors(array $decoded, GreekVerse $first): array
    {
        $errors = [];

        if (($decoded['format'] ?? null) !== self::FORMAT_VERSION) {
            $errors[] = 'The "format" field must be '.self::FORMAT_VERSION.'.';
        }

        if (($decoded['usxCode'] ?? null) !== $first->usx_code) {
            $errors[] = "The \"usxCode\" field must be \"{$first->usx_code}\".";
        }

        if (($decoded['chapter'] ?? null) !== $first->chapter) {
            $errors[] = "The \"chapter\" field must be {$first->chapter}.";
        }

        return $errors;
    }

    /**
     * Errors and warnings of the verses of a chapter.
     *
     * @param  array<string, mixed>  $decoded
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function verseErrors(array $decoded, Collection $chapterVerses): array
    {
        $analysedVerses = $decoded['verses'] ?? null;

        if (!is_array($analysedVerses)) {
            return [['The "verses" field is missing or is not an array.'], []];
        }

        $expectedVerseNumbers = $chapterVerses->map(fn (GreekVerse $verse): int => $verse->verse)->values()->all();
        $actualVerseNumbers = array_map(
            fn ($analysedVerse): mixed => is_array($analysedVerse) ? ($analysedVerse['verse'] ?? null) : null,
            array_values($analysedVerses)
        );

        if ($actualVerseNumbers !== $expectedVerseNumbers) {
            return [[sprintf(
                'The verses of the file (%s) do not match the verses of the chapter (%s).',
                $this->describeVerseNumbers($actualVerseNumbers),
                $this->describeVerseNumbers($expectedVerseNumbers)
            )], []];
        }

        $errors = [];
        $warnings = [];

        foreach (array_values($analysedVerses) as $i => $analysedVerse) {
            $verse = $chapterVerses->values()->get($i);
            [$verseErrors, $verseWarnings] = $this->singleVerseErrors($analysedVerse, $verse);

            $errors = array_merge($errors, $verseErrors);
            $warnings = array_merge($warnings, $verseWarnings);
        }

        return [$errors, $warnings];
    }

    /**
     * @param  array<string, mixed>  $analysedVerse
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function singleVerseErrors(array $analysedVerse, GreekVerse $verse): array
    {
        $label = "{$verse->chapter},{$verse->verse}";
        $errors = [];
        $warnings = [];

        $expectedText = str_replace('¶', '', $verse->text);

        if (!$this->greekMatches($analysedVerse['greekText'] ?? null, $expectedText)) {
            $errors[] = "{$label}: the \"greekText\" field differs from the stored Greek text.";
        }

        $segments = $analysedVerse['segments'] ?? null;

        if (!is_array($segments) || $segments === []) {
            $errors[] = "{$label}: the \"segments\" field is missing or empty.";

            return [$errors, $warnings];
        }

        $words = $verse->annotatedWords();
        $seenIndexes = [];
        $previousFirstIndex = -1;

        foreach (array_values($segments) as $segmentPosition => $segment) {
            $segmentLabel = "{$label} segment #{$segmentPosition}";

            if (!is_array($segment)) {
                $errors[] = "{$segmentLabel}: not an object.";

                continue;
            }

            $wordIndexes = $segment['wordIndexes'] ?? null;

            if (!is_array($wordIndexes) || $wordIndexes === [] || array_filter($wordIndexes, fn ($index): bool => !is_int($index)) !== []) {
                $errors[] = "{$segmentLabel}: \"wordIndexes\" must be a non-empty array of integers.";

                continue;
            }

            $wordIndexes = array_values($wordIndexes);

            foreach ($wordIndexes as $index) {
                if (!isset($words[$index])) {
                    $errors[] = "{$segmentLabel}: word index {$index} is outside the verse (it has ".count($words).' words).';

                    continue 2;
                }

                if (isset($seenIndexes[$index])) {
                    $errors[] = "{$segmentLabel}: word index {$index} is covered by more than one segment.";

                    continue 2;
                }

                $seenIndexes[$index] = true;
            }

            if ($wordIndexes !== $this->sorted($wordIndexes)) {
                $errors[] = "{$segmentLabel}: the word indexes are not in increasing order.";
            }

            if ($wordIndexes[0] <= $previousFirstIndex) {
                $errors[] = "{$segmentLabel}: the segments are not ordered by their first word index.";
            }

            $previousFirstIndex = $wordIndexes[0];

            $expectedGreek = implode(' ', array_map(fn (int $index): string => $words[$index]['printed'], $wordIndexes));

            if (!$this->greekMatches($segment['greek'] ?? null, $expectedGreek)) {
                $errors[] = "{$segmentLabel}: the \"greek\" field must be \"{$expectedGreek}\".";
            }

            if (!is_string($segment['meaning'] ?? null) || trim((string) $segment['meaning']) === '') {
                $errors[] = "{$segmentLabel}: the \"meaning\" field is missing or empty.";
            }

            $errors = array_merge($errors, $this->alternativesErrors($segment, $segmentLabel));

            if ($this->isNonContiguous($wordIndexes)) {
                $warnings[] = "{$segmentLabel}: the word indexes are not contiguous.";
            }
        }

        $missingIndexes = array_values(array_diff(array_keys($words), array_keys($seenIndexes)));

        if ($missingIndexes !== []) {
            $errors[] = "{$label}: the segments do not cover the word indexes ".implode(', ', $missingIndexes).'.';
        }

        return [$errors, $warnings];
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array<int, string>
     */
    private function alternativesErrors(array $segment, string $segmentLabel): array
    {
        if (!array_key_exists('alternatives', $segment)) {
            return [];
        }

        $alternatives = $segment['alternatives'];

        if (!is_array($alternatives) || $alternatives === []) {
            return ["{$segmentLabel}: \"alternatives\" must be a non-empty array of strings when present."];
        }

        foreach ($alternatives as $alternative) {
            if (!is_string($alternative) || trim($alternative) === '') {
                return ["{$segmentLabel}: \"alternatives\" must contain non-empty strings only."];
            }
        }

        return [];
    }

    /**
     * Whether two Greek strings are the same sequence of Unicode characters, ignoring how those
     * characters are encoded.
     *
     * The database stores GREEK ANO TELEIA (U+0387) and GREEK QUESTION MARK (U+037E), which are
     * canonically equivalent to MIDDLE DOT (U+00B7) and SEMICOLON (U+003B). Any NFC-normalising
     * step in the tooling that writes an analysis file silently swaps them, so a byte-wise
     * comparison would reject a text that is character for character identical. Comparing under
     * Unicode normalisation keeps the "letter by letter" guarantee while looking past the
     * encoding.
     */
    private function greekMatches(mixed $actual, string $expected): bool
    {
        return is_string($actual)
            && \Normalizer::normalize($actual, \Normalizer::FORM_C) === \Normalizer::normalize($expected, \Normalizer::FORM_C);
    }

    /**
     * @param  array<int, int>  $wordIndexes
     * @return array<int, int>
     */
    private function sorted(array $wordIndexes): array
    {
        sort($wordIndexes);

        return $wordIndexes;
    }

    /**
     * @param  array<int, int>  $wordIndexes
     */
    private function isNonContiguous(array $wordIndexes): bool
    {
        return max($wordIndexes) - min($wordIndexes) + 1 !== count($wordIndexes);
    }

    /**
     * @param  array<int, mixed>  $verseNumbers
     */
    private function describeVerseNumbers(array $verseNumbers): string
    {
        if ($verseNumbers === []) {
            return 'none';
        }

        return implode(', ', array_map(fn ($verseNumber): string => is_int($verseNumber) ? (string) $verseNumber : '?', $verseNumbers));
    }

    private function pathFor(string $chapterKey): string
    {
        $directory = (string) $this->option('dir');

        if (!Str::startsWith($directory, '/')) {
            $directory = base_path($directory);
        }

        return rtrim($directory, '/')."/{$chapterKey}.json";
    }

    /**
     * The chapters that have no analysis file yet.
     *
     * @param  Collection<int, array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}>  $results
     */
    private function reportMissing(Collection $results): int
    {
        $missing = $results->reject(fn (array $result): bool => $result['exists'])->values();

        if ($this->option('json')) {
            $this->line(json_encode($missing->pluck('chapter')->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($missing as $result) {
            $this->line($result['chapter']);
        }

        $this->info("{$missing->count()} chapter(s) missing out of {$results->count()}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}>  $results
     */
    private function report(Collection $results): int
    {
        $failed = $results->filter(fn (array $result): bool => $result['errors'] !== []);

        if ($this->option('json')) {
            $this->line(json_encode($results->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        foreach ($results as $result) {
            if ($result['errors'] === []) {
                $this->line("<info>OK</info>    {$result['chapter']}".($result['warnings'] === [] ? '' : ' ('.count($result['warnings']).' warning(s))'));
            } else {
                $this->line("<error>FAIL</error>  {$result['chapter']}");
            }

            foreach ($result['errors'] as $error) {
                $this->line("        {$error}");
            }

            foreach ($result['warnings'] as $warning) {
                $this->line("        <comment>warning:</comment> {$warning}");
            }
        }

        if ($failed->isEmpty()) {
            $this->info("{$results->count()} chapter(s) validated, all good.");

            return self::SUCCESS;
        }

        $this->error("{$failed->count()} of {$results->count()} chapter(s) failed validation.");

        return self::FAILURE;
    }
}
