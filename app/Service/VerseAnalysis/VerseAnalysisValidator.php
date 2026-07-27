<?php

namespace SzentirasHu\Service\VerseAnalysis;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use SzentirasHu\Models\GreekVerse;

class VerseAnalysisValidator
{
    /**
     * The only file format version understood by the importer and validator.
     */
    public const FORMAT_VERSION = 1;

    /**
     * @param  array<string, mixed>  $analysis
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $analysis, Collection $chapterVerses): array
    {
        if ($chapterVerses->isEmpty()) {
            return [
                'errors' => ['No matching Greek verses exist in the database.'],
                'warnings' => [],
            ];
        }

        $firstVerse = $chapterVerses->firstOrFail();
        [$verseErrors, $verseWarnings] = $this->verseErrors($analysis, $chapterVerses);

        return [
            'errors' => array_merge($this->headerErrors($analysis, $firstVerse), $verseErrors),
            'warnings' => $verseWarnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, string>
     */
    private function headerErrors(array $analysis, GreekVerse $firstVerse): array
    {
        $errors = [];

        if (($analysis['format'] ?? null) !== self::FORMAT_VERSION) {
            $errors[] = 'The "format" field must be '.self::FORMAT_VERSION.'.';
        }

        if (($analysis['usxCode'] ?? null) !== $firstVerse->usx_code) {
            $errors[] = "The \"usxCode\" field must be \"{$firstVerse->usx_code}\".";
        }

        if (($analysis['chapter'] ?? null) !== $firstVerse->chapter) {
            $errors[] = "The \"chapter\" field must be {$firstVerse->chapter}.";
        }

        if (($analysis['greekSource'] ?? null) !== $firstVerse->source) {
            $errors[] = "The \"greekSource\" field must be \"{$firstVerse->source}\".";
        }

        foreach (['book', 'createdBy'] as $field) {
            if (! is_string($analysis[$field] ?? null) || trim((string) $analysis[$field]) === '') {
                $errors[] = "The \"{$field}\" field is missing or empty.";
            }
        }

        if (! $this->isDate($analysis['createdAt'] ?? null)) {
            $errors[] = 'The "createdAt" field must be a valid YYYY-MM-DD date.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function verseErrors(array $analysis, Collection $chapterVerses): array
    {
        $analysedVerses = $analysis['verses'] ?? null;

        if (! is_array($analysedVerses)) {
            return [['The "verses" field is missing or is not an array.'], []];
        }

        $expectedVerseNumbers = $chapterVerses
            ->map(fn (GreekVerse $verse): int => $verse->verse)
            ->values()
            ->all();
        $actualVerseNumbers = array_map(
            fn ($analysedVerse): mixed => is_array($analysedVerse)
                ? ($analysedVerse['verse'] ?? null)
                : null,
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

        foreach (array_values($analysedVerses) as $index => $analysedVerse) {
            $verse = $chapterVerses->values()->get($index);
            [$verseErrors, $verseWarnings] = $this->singleVerseErrors($analysedVerse, $verse);

            array_push($errors, ...$verseErrors);
            array_push($warnings, ...$verseWarnings);
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

        if (($analysedVerse['gepi'] ?? null) !== $verse->gepi) {
            $errors[] = "{$label}: the \"gepi\" field must be \"{$verse->gepi}\".";
        }

        $expectedText = str_replace('¶', '', $verse->text);

        if (! $this->greekMatches($analysedVerse['greekText'] ?? null, $expectedText)) {
            $errors[] = "{$label}: the \"greekText\" field differs from the stored Greek text.";
        }

        $segments = $analysedVerse['segments'] ?? null;

        if (! is_array($segments) || $segments === []) {
            $errors[] = "{$label}: the \"segments\" field is missing or empty.";

            return [$errors, $warnings];
        }

        $words = $verse->annotatedWords();
        $seenIndexes = [];
        $previousFirstIndex = -1;

        foreach (array_values($segments) as $segmentPosition => $segment) {
            $segmentLabel = "{$label} segment #{$segmentPosition}";

            if (! is_array($segment)) {
                $errors[] = "{$segmentLabel}: not an object.";

                continue;
            }

            $wordIndexes = $segment['wordIndexes'] ?? null;

            if (
                ! is_array($wordIndexes)
                || $wordIndexes === []
                || array_filter($wordIndexes, fn ($wordIndex): bool => ! is_int($wordIndex)) !== []
            ) {
                $errors[] = "{$segmentLabel}: \"wordIndexes\" must be a non-empty array of integers.";

                continue;
            }

            $wordIndexes = array_values($wordIndexes);

            foreach ($wordIndexes as $wordIndex) {
                if (! isset($words[$wordIndex])) {
                    $errors[] = "{$segmentLabel}: word index {$wordIndex} is outside the verse (it has ".count($words).' words).';

                    continue 2;
                }

                if (isset($seenIndexes[$wordIndex])) {
                    $errors[] = "{$segmentLabel}: word index {$wordIndex} is covered by more than one segment.";

                    continue 2;
                }

                $seenIndexes[$wordIndex] = true;
            }

            if ($wordIndexes !== $this->sorted($wordIndexes)) {
                $errors[] = "{$segmentLabel}: the word indexes are not in increasing order.";
            }

            if ($wordIndexes[0] <= $previousFirstIndex) {
                $errors[] = "{$segmentLabel}: the segments are not ordered by their first word index.";
            }

            $previousFirstIndex = $wordIndexes[0];
            $expectedGreek = implode(
                ' ',
                array_map(fn (int $wordIndex): string => $words[$wordIndex]['printed'], $wordIndexes)
            );

            if (! $this->greekMatches($segment['greek'] ?? null, $expectedGreek)) {
                $errors[] = "{$segmentLabel}: the \"greek\" field must be \"{$expectedGreek}\".";
            }

            if (! is_string($segment['meaning'] ?? null) || trim((string) $segment['meaning']) === '') {
                $errors[] = "{$segmentLabel}: the \"meaning\" field is missing or empty.";
            }

            array_push($errors, ...$this->alternativesErrors($segment, $segmentLabel));

            if (array_key_exists('note', $segment) && (! is_string($segment['note']) || trim($segment['note']) === '')) {
                $errors[] = "{$segmentLabel}: \"note\" must be a non-empty string when present.";
            }

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
        if (! array_key_exists('alternatives', $segment)) {
            return [];
        }

        $alternatives = $segment['alternatives'];

        if (! is_array($alternatives) || $alternatives === []) {
            return ["{$segmentLabel}: \"alternatives\" must be a non-empty array of strings when present."];
        }

        foreach ($alternatives as $alternative) {
            if (! is_string($alternative) || trim($alternative) === '') {
                return ["{$segmentLabel}: \"alternatives\" must contain non-empty strings only."];
            }
        }

        return [];
    }

    private function greekMatches(mixed $actual, string $expected): bool
    {
        return is_string($actual)
            && \Normalizer::normalize($actual, \Normalizer::FORM_C)
                === \Normalizer::normalize($expected, \Normalizer::FORM_C);
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

        return implode(
            ', ',
            array_map(
                fn ($verseNumber): string => is_int($verseNumber) ? (string) $verseNumber : '?',
                $verseNumbers
            )
        );
    }

    private function isDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
