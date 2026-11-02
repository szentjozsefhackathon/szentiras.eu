<?php

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Facades\Config;

/**
 * A full text search that answers with the bare hits and their Sphinx weights, for callers
 * that rank the hits themselves, such as Magister's hybrid search.
 */
class ExtendedSearchParams
{
    public string $text = '';

    /**
     * The translations to search in, the preferred first: with {@see $onePerVerse}, of equally
     * weighted translations of a verse the earliest is reported.
     *
     * @var array<int, int>
     */
    public array $translationIds = [];

    /**
     * The books to search in, keyed by USX code. Empty means the whole Bible.
     *
     * @var array<string, mixed>
     */
    public array $usxCodes = [];

    /**
     * The maximum number of hits returned. Defaults to the site wide search limit.
     */
    public ?int $limit = null;

    /**
     * The share of the words, between 0 and 1, a verse has to contain to be a hit. Null means
     * every word is needed. A verse with more of the words still weighs more.
     */
    public ?float $quorum = null;

    /**
     * Whether a verse is a single hit, in its best matching translation, instead of one hit
     * per translation.
     */
    public bool $onePerVerse = false;

    public function resolvedLimit(): int
    {
        return $this->limit ?? (int) Config::get('settings.sphinxSearchLimit');
    }
}
