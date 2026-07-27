<?php

namespace SzentirasHu\Service\VerseAnalysis;

use UnexpectedValueException;

class VerseAnalysisSemanticValidator
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $semantic
     * @return array{verses: array<int, array{verse: int, segments: array<int, array<string, mixed>>}>}
     */
    public function validate(array $source, array $semantic): array
    {
        if (array_keys($semantic) !== ['verses'] || ! is_array($semantic['verses'])) {
            throw new UnexpectedValueException('Semantic output must contain only a "verses" array.');
        }

        $sourceVerses = $source['verses'] ?? null;

        if (! is_array($sourceVerses)) {
            throw new UnexpectedValueException('The compact source does not contain a valid "verses" array.');
        }

        $expectedVerseNumbers = array_map(
            fn (array $verse): mixed => $verse['verse'] ?? null,
            $sourceVerses,
        );
        $actualVerseNumbers = array_map(
            fn ($verse): mixed => is_array($verse) ? ($verse['verse'] ?? null) : null,
            $semantic['verses'],
        );

        if ($actualVerseNumbers !== $expectedVerseNumbers) {
            throw new UnexpectedValueException('Semantic verse numbers do not match the compact source.');
        }

        $normalizedVerses = [];

        foreach ($semantic['verses'] as $versePosition => $semanticVerse) {
            $sourceVerse = $sourceVerses[$versePosition];
            $verseNumber = $expectedVerseNumbers[$versePosition];

            if (! is_int($verseNumber) || ! is_array($semanticVerse)) {
                throw new UnexpectedValueException("Invalid semantic verse at position {$versePosition}.");
            }

            if (array_diff(array_keys($semanticVerse), ['verse', 'segments']) !== []) {
                throw new UnexpectedValueException("Verse {$verseNumber} contains unsupported fields.");
            }

            $segments = $semanticVerse['segments'] ?? null;

            if (! is_array($segments) || $segments === []) {
                throw new UnexpectedValueException("Verse {$verseNumber} must contain at least one segment.");
            }

            $wordCount = is_array($sourceVerse['words'] ?? null)
                ? count($sourceVerse['words'])
                : 0;

            if ($wordCount < 1) {
                throw new UnexpectedValueException("Verse {$verseNumber} has no source words.");
            }

            $seenIndexes = [];
            $previousFirstIndex = -1;
            $normalizedSegments = [];

            foreach (array_values($segments) as $segmentPosition => $segment) {
                if (! is_array($segment)) {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segment {$segmentPosition} is not an object."
                    );
                }

                if (
                    array_diff(
                        array_keys($segment),
                        ['wordIndexes', 'meaning', 'alternatives', 'note'],
                    ) !== []
                ) {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segment {$segmentPosition} contains unsupported fields."
                    );
                }

                $wordIndexes = $segment['wordIndexes'] ?? null;

                if (
                    ! is_array($wordIndexes)
                    || $wordIndexes === []
                    || array_filter($wordIndexes, fn ($index): bool => ! is_int($index)) !== []
                ) {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segment {$segmentPosition} has invalid word indexes."
                    );
                }

                $wordIndexes = array_values($wordIndexes);

                if ($wordIndexes !== $this->sorted($wordIndexes)) {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segment {$segmentPosition} word indexes are not sorted."
                    );
                }

                if ($wordIndexes[0] <= $previousFirstIndex) {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segments are not ordered by their first word index."
                    );
                }

                foreach ($wordIndexes as $wordIndex) {
                    if ($wordIndex < 0 || $wordIndex >= $wordCount) {
                        throw new UnexpectedValueException(
                            "Verse {$verseNumber} word index {$wordIndex} is outside the source verse."
                        );
                    }

                    if (isset($seenIndexes[$wordIndex])) {
                        throw new UnexpectedValueException(
                            "Verse {$verseNumber} word index {$wordIndex} occurs in multiple segments."
                        );
                    }

                    $seenIndexes[$wordIndex] = true;
                }

                $meaning = $segment['meaning'] ?? null;

                if (! is_string($meaning) || trim($meaning) === '') {
                    throw new UnexpectedValueException(
                        "Verse {$verseNumber} segment {$segmentPosition} has no meaning."
                    );
                }

                $normalizedSegment = [
                    'wordIndexes' => $wordIndexes,
                    'meaning' => trim($meaning),
                ];

                if (array_key_exists('alternatives', $segment)) {
                    $normalizedSegment['alternatives'] = $this->alternatives(
                        $segment['alternatives'],
                        trim($meaning),
                        $verseNumber,
                        $segmentPosition,
                    );
                }

                if (array_key_exists('note', $segment)) {
                    if (! is_string($segment['note']) || trim($segment['note']) === '') {
                        throw new UnexpectedValueException(
                            "Verse {$verseNumber} segment {$segmentPosition} has an invalid note."
                        );
                    }

                    $normalizedSegment['note'] = trim($segment['note']);
                }

                $previousFirstIndex = $wordIndexes[0];
                $normalizedSegments[] = $normalizedSegment;
            }

            $missingIndexes = array_values(array_diff(range(0, $wordCount - 1), array_keys($seenIndexes)));

            if ($missingIndexes !== []) {
                throw new UnexpectedValueException(
                    "Verse {$verseNumber} does not cover word indexes ".implode(', ', $missingIndexes).'.'
                );
            }

            $normalizedVerses[] = [
                'verse' => $verseNumber,
                'segments' => $normalizedSegments,
            ];
        }

        return ['verses' => $normalizedVerses];
    }

    /**
     * @return array<int, string>
     */
    private function alternatives(
        mixed $alternatives,
        string $meaning,
        int $verseNumber,
        int $segmentPosition,
    ): array {
        if (! is_array($alternatives) || $alternatives === []) {
            throw new UnexpectedValueException(
                "Verse {$verseNumber} segment {$segmentPosition} has invalid alternatives."
            );
        }

        $normalized = [];

        foreach ($alternatives as $alternative) {
            if (! is_string($alternative) || trim($alternative) === '') {
                throw new UnexpectedValueException(
                    "Verse {$verseNumber} segment {$segmentPosition} has an empty alternative."
                );
            }

            $normalized[] = trim($alternative);
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new UnexpectedValueException(
                "Verse {$verseNumber} segment {$segmentPosition} has duplicate alternatives."
            );
        }

        if (in_array($meaning, $normalized, true)) {
            throw new UnexpectedValueException(
                "Verse {$verseNumber} segment {$segmentPosition} repeats its meaning as an alternative."
            );
        }

        return $normalized;
    }

    /**
     * @param  array<int, int>  $values
     * @return array<int, int>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
