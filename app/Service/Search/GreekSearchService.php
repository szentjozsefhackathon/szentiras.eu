<?php

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Sphinx\SphinxSearch;

/**
 * Searches the Greek New Testament and pairs every hit with the Hungarian verse at the same
 * place, so that a Greek search returns the very same result structure as a Hungarian full
 * text search does.
 *
 * The search itself runs over the Greek verses, which is what the limit applies to: one
 * Greek verse may bring in as many Hungarian verses as there are translations searched.
 */
class GreekSearchService
{
    /**
     * The verse type carrying the Greek aligned Hungarian text.
     */
    private const VERSE_TYPES = [901];

    public function __construct(private SearchService $searchService)
    {
    }

    /**
     * @return array{resultsByBookNumber: array<mixed>, results: array<mixed>, hitCount: int}|null
     */
    public function search(GreekSearchParams $params): ?array
    {
        $greekVersesPerGepi = $this->findGreekVerses($params)->keyBy('gepi');

        if ($greekVersesPerGepi->isEmpty()) {
            return null;
        }

        $verses = $this->findTranslatedVerses($params, $greekVersesPerGepi->keys()->all());

        if ($verses->isEmpty()) {
            return null;
        }

        return $this->searchService->handleFullTextResults(
            $this->buildSearchResult($verses, $greekVersesPerGepi),
            $params->toFullTextSearchParams()
        );
    }

    /**
     * The number of Greek verses matching the search, regardless of the limit, so that a
     * caller can tell whether the returned verses are only part of the hits.
     */
    public function countHits(GreekSearchParams $params): int
    {
        if ($params->mode === GreekSearchMode::Verse) {
            $sphinxClient = $this->greekTextClient($params);
            $sphinxClient->countOnly(true);
            $sphinxResult = $sphinxClient->getGreekNormalizations();

            return (int) ($sphinxResult[0]['hitcount'] ?? 0);
        }

        return $this->greekVerseQuery($params)?->count() ?? 0;
    }

    /**
     * @return Collection<int, GreekVerse>
     */
    private function findGreekVerses(GreekSearchParams $params): Collection
    {
        if ($params->mode === GreekSearchMode::Verse) {
            return $this->findByGreekText($params);
        }

        return $this->greekVerseQuery($params)
            ?->orderBy('usx_code')
            ->orderBy('chapter')
            ->orderBy('verse')
            ->limit($params->resolvedLimit())
            ->get() ?? collect();
    }

    /**
     * The database query behind the modes that search the words of the verses, or null when
     * the search text holds nothing to search for.
     *
     * @return \Illuminate\Database\Eloquent\Builder<GreekVerse>|null
     */
    private function greekVerseQuery(GreekSearchParams $params): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = match ($params->mode) {
            GreekSearchMode::Lemma => $this->lemmaQuery($params),
            GreekSearchMode::Strong => $this->strongQuery($params),
            GreekSearchMode::Verse => null,
        };

        if ($query && $params->usxCodes !== []) {
            // The book filter has to narrow the Greek verses themselves, otherwise the limit
            // would be spent on verses that the book filter throws away afterwards.
            $query->whereIn('usx_code', array_keys($params->usxCodes));
        }

        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<GreekVerse>|null
     */
    private function strongQuery(GreekSearchParams $params): ?\Illuminate\Database\Eloquent\Builder
    {
        $numbers = array_values(array_unique(array_map(
            fn (string $number): int => (int) $number,
            array_filter(preg_split('/\D+/', $params->text) ?: [], fn (string $part): bool => $part !== '')
        )));

        if ($numbers === []) {
            return null;
        }

        $query = GreekVerse::query();

        if ($params->rule === GreekSearchRule::Any) {
            return $query->whereHas('strongWords', fn ($strongWords) => $strongWords->whereIn('number', $numbers));
        }

        foreach ($numbers as $number) {
            $query->whereHas('strongWords', fn ($strongWords) => $strongWords->where('number', $number));
        }

        return $query;
    }

    /**
     * The query matching the Strong words of the verses, or null when the search text holds
     * no usable word at all.
     *
     * @return \Illuminate\Database\Eloquent\Builder<GreekVerse>|null
     */
    private function lemmaQuery(GreekSearchParams $params): ?\Illuminate\Database\Eloquent\Builder
    {
        $words = $this->lemmaWords($params->text);

        if ($words === []) {
            return null;
        }

        // The word conditions are grouped, so that an `any` search still keeps whatever the
        // caller narrowed the search to, instead of the book filter binding to the last word.
        return GreekVerse::query()->where(function ($query) use ($words, $params): void {
            foreach ($words as $word) {
                $pattern = "\\y{$word}\\y";

                if ($params->rule === GreekSearchRule::Any) {
                    $query->orWhere('strong_normalizations', '~*', $pattern);
                } else {
                    $query->where('strong_normalizations', '~*', $pattern);
                }
            }
        });
    }

    /**
     * The searched words, stripped of everything that is not a letter or a digit: they are
     * interpolated into a regular expression, and the normalized Strong words they are
     * matched against hold nothing else either.
     *
     * @return array<int, string>
     */
    private function lemmaWords(string $text): array
    {
        $words = array_map(
            fn (string $word): string => preg_replace('/[^\p{L}\p{N}]+/u', '', $word),
            explode(' ', mb_strtolower($text))
        );

        return array_values(array_filter($words));
    }

    /**
     * @return Collection<int, GreekVerse>
     */
    private function findByGreekText(GreekSearchParams $params): Collection
    {
        if (trim($params->text) === '') {
            return collect();
        }

        $sphinxResult = $this->greekTextClient($params)->getGreekNormalizations();

        if (! $sphinxResult) {
            return collect();
        }

        $ids = array_column($sphinxResult, 'id');

        return $ids === [] ? collect() : GreekVerse::whereIn('id', $ids)->get();
    }

    private function greekTextClient(GreekSearchParams $params): SphinxSearch
    {
        $sphinxClient = new SphinxSearch($params->text);
        $sphinxClient->limit($params->resolvedLimit());

        if ($params->usxCodes !== []) {
            $sphinxClient->filter('usx_code', array_keys($params->usxCodes));
        }

        return $sphinxClient;
    }

    /**
     * The Hungarian verses belonging to the Greek hits. They need no limit of their own: the
     * Greek verses are already limited, and every translation of a hit should be shown.
     *
     * @param  array<int, string>  $gepis
     * @return Collection<int, Verse>
     */
    private function findTranslatedVerses(GreekSearchParams $params, array $gepis): Collection
    {
        $query = Verse::query()
            ->whereIn('gepi', $gepis)
            ->whereIn('tip', self::VERSE_TYPES);

        if ($params->translationId) {
            $query->where('trans', $params->translationId);
        }

        if ($params->usxCodes !== []) {
            $query->whereIn('usx_code', array_keys($params->usxCodes));
        }

        return $query
            ->orderBy('tip')
            ->orderBy('usx_code')
            ->orderBy('chapter')
            ->orderBy('numv')
            ->get();
    }

    /**
     * Dresses the Greek hits up as a full text search result, adding the Greek text and its
     * word by word analysis to every verse.
     *
     * @param  Collection<int, Verse>  $verses
     * @param  Collection<string, GreekVerse>  $greekVersesPerGepi
     */
    private function buildSearchResult(Collection $verses, Collection $greekVersesPerGepi): FullTextSearchResult
    {
        $result = new FullTextSearchResult();
        $result->verseIds = [];
        $result->verses = [];

        foreach ($verses as $verse) {
            $greekVerse = $greekVersesPerGepi[$verse->gepi];
            $result->verseIds[] = $verse->id;
            $result->verses[$verse->id] = [
                'id' => $verse->id,
                'trans' => $verse->trans,
                'usx_code' => $verse->usx_code,
                'chapter' => $verse->chapter,
                'numv' => $verse->numv,
                'gepi' => $verse->gepi,
                'tip' => $verse->tip,
                'weight()' => 1,
                'greekText' => str_replace('¶', '', $greekVerse->text),
                'greekTransliteration' => $greekVerse->transliteration,
                'greekWords' => $greekVerse->annotatedWords(),
            ];
        }

        $result->hitCount = count($result->verseIds);

        return $result;
    }
}
