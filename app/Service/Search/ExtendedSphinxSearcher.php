<?php

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Facades\Config;
use SzentirasHu\Service\Sphinx\SphinxSearch;

/**
 * Runs an {@see ExtendedSearchParams} search on the Sphinx verse index. It matches the same
 * way the full text search does, so its phrase and exact form preferences show in the
 * weights, and with a quorum it also accepts verses that miss some of the words.
 */
class ExtendedSphinxSearcher
{
    /**
     * A word standing in more than this share of the verses ("a", "az", "és", "hogy") does not
     * count in the quorum: a verse holding it tells nothing about the verse searched for.
     */
    public const FREQUENT_WORD_SHARE = 0.1;

    /**
     * The default ranking plus the number of the searched words a field holds: a quorum match
     * has no phrase to measure, so without the word count a verse with four of the words would
     * weigh the same as one with a single word. A single word and a whole phrase weigh as
     * before relative to each other.
     */
    public const RANKER = 'sum((lcs + word_count - 1) * user_weight) * 1000 + bm25a(1.2, 0.75) * 1000';

    public function __construct(private ExtendedSearchParams $params) {}

    public function get(): ExtendedSearchResult
    {
        if (SphinxSearcher::sanitize($this->params->text) === '' || $this->params->translationIds === []) {
            return new ExtendedSearchResult([], 0);
        }

        $sphinxClient = new SphinxSearch('');
        $keywords = $sphinxClient->keywords(SphinxSearcher::sanitize($this->params->text));
        $questionWords = array_column($keywords, 'word');
        $countedWords = self::countedWords($keywords);
        $sphinxClient->match($this->matchExpression($countedWords));
        $sphinxClient->ranker(self::RANKER);
        $sphinxClient->limit($this->params->resolvedLimit());
        $sphinxClient->filter('trans', $this->params->translationIds);
        $sphinxClient->filter('tip', self::verseTextTypes());
        if ($this->params->usxCodes !== []) {
            $sphinxClient->filter('usx_code', array_keys($this->params->usxCodes));
        }
        if ($this->params->onePerVerse) {
            $sphinxClient->groupBy(['gepi'], $this->translationPreference());
        } else {
            $sphinxClient->groupBy(['gepi', 'trans']);
        }

        $rows = $sphinxClient->get();
        $totalHitCount = $sphinxClient->totalFound();
        $ids = array_map(fn (array $row): int => (int) $row['id'], $rows);
        $countedWordFactors = $sphinxClient->verseFieldFactors(self::anyWordMatch($countedWords), $ids);
        $questionFactors = $sphinxClient->verseFieldFactors(self::anyWordMatch($questionWords), $ids);
        $hits = array_map(fn (array $row): ExtendedSearchHit => ExtendedSearchHit::fromSphinxRow($row, [
            'lcs' => $questionFactors[(int) $row['id']]['lcs'] ?? 0,
            'word_count' => $countedWordFactors[(int) $row['id']]['word_count'] ?? 0,
            'exact_hit' => $questionFactors[(int) $row['id']]['exact_hit'] ?? 0,
        ]), $rows);

        return new ExtendedSearchResult($hits, $totalHitCount, count($countedWords), count($questionWords));
    }

    /**
     * The full text search's match, and with a quorum a match of that share of the counted
     * words in the verse field.
     *
     * @param  array<int, string>  $countedWords
     */
    public function matchExpression(array $countedWords): string
    {
        $expression = SphinxSearcher::matchExpressionFor($this->params->text);

        if ($this->params->quorum === null || count($countedWords) < 2) {
            return $expression;
        }

        return $expression.sprintf(' | ( @verse "%s"/%.2F )', implode(' ', $countedWords), $this->params->quorum);
    }

    /**
     * The words of the search, each once, that the quorum and the word count count. The
     * frequent ones are left out, otherwise "a" and "az" would fill in for the words remembered
     * wrongly; but when every word is frequent, all of them count.
     *
     * @param  array<int, array{word: string, share: float}>  $keywords  the words of the search as the index holds them, with the share of the verses each stands in
     * @return array<int, string>
     */
    public static function countedWords(array $keywords): array
    {
        $words = array_column(array_filter($keywords, fn (array $keyword): bool => $keyword['share'] <= self::FREQUENT_WORD_SHARE), 'word');

        return array_values(array_unique($words !== [] ? $words : array_column($keywords, 'word')));
    }

    /**
     * The match the word factors are measured with: any of the words, in the verse text as
     * written, so that the factors tell how many of the words and how long a run of them the
     * verse holds.
     *
     * @param  array<int, string>  $words
     */
    public static function anyWordMatch(array $words): string
    {
        return sprintf('@verse "%s"/1', implode(' ', $words));
    }

    /**
     * The position of the translation among the searched ones, so that of equally weighted
     * translations of a verse the first one searched is reported.
     */
    public function translationPreference(): string
    {
        $expression = (string) count($this->params->translationIds);
        foreach (array_reverse($this->params->translationIds, true) as $position => $translationId) {
            $expression = sprintf('IF(trans=%d,%d,%s)', $translationId, $position, $expression);
        }

        return $expression;
    }

    /**
     * The verse types holding the text of a verse in any translation; the index also holds
     * headings, which carry the place of the verse they stand before.
     *
     * @return array<int, int>
     */
    public static function verseTextTypes(): array
    {
        return collect(Config::get('translations.definitions'))
            ->flatMap(fn (array $definition): array => [
                ...$definition['verseTypes']['text'] ?? [],
                ...$definition['verseTypes']['poemLine'] ?? [],
            ])
            ->unique()
            ->values()
            ->all();
    }
}
