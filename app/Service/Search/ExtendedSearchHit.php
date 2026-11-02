<?php

namespace SzentirasHu\Service\Search;

/**
 * A verse found by the extended search, in one translation.
 *
 * The weight orders the hits, but its scale moves with the index. The word factors do not:
 * they measure the words of the search in the verse text as written, so that divided by
 * {@see ExtendedSearchResult::$queryWordCount} and {@see ExtendedSearchResult::$questionWordCount}
 * they tell how much of the question the verse holds.
 */
class ExtendedSearchHit
{
    /**
     * @param  int  $longestRun  the most words of the search standing in the verse in a row, in the search's order
     * @param  int  $wordCount  the distinct counted words of the search the verse holds
     * @param  bool  $exactHit  whether the verse is exactly the words of the search
     */
    public function __construct(
        public string $usxCode,
        public int $chapter,
        public int $verse,
        public int $translationId,
        public float $weight,
        public int $longestRun = 0,
        public int $wordCount = 0,
        public bool $exactHit = false,
    ) {}

    /**
     * @param  array<string, mixed>  $row  a row of the Sphinx verse index
     * @param  array{lcs: int, word_count: int, exact_hit: int}|null  $factors  the row's word factors
     */
    public static function fromSphinxRow(array $row, ?array $factors = null): self
    {
        $gepiParts = explode('_', (string) $row['gepi']);

        return new self(
            usxCode: (string) $row['usx_code'],
            chapter: (int) $row['chapter'],
            verse: (int) end($gepiParts),
            translationId: (int) $row['trans'],
            weight: (float) $row['weight()'],
            longestRun: $factors['lcs'] ?? 0,
            wordCount: $factors['word_count'] ?? 0,
            exactHit: ($factors['exact_hit'] ?? 0) === 1,
        );
    }
}
