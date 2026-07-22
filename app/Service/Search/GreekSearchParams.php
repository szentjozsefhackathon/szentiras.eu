<?php

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Facades\Config;

class GreekSearchParams
{
    public string $text = '';

    public GreekSearchMode $mode = GreekSearchMode::Lemma;

    /**
     * Only used in {@see GreekSearchMode::Lemma}; the Greek text search expresses the same
     * thing with the `|` operator inside the searched text.
     */
    public GreekSearchRule $rule = GreekSearchRule::All;

    /**
     * The Hungarian translation to show next to the Greek text. All of them when null.
     */
    public ?int $translationId = null;

    /**
     * The books to search in, keyed by USX code. Empty means the whole Bible.
     *
     * @var array<string, mixed>
     */
    public array $usxCodes = [];

    /**
     * One of `verse`, `chapter` or `book`.
     */
    public ?string $grouping = null;

    /**
     * The maximum number of Greek verses to match. Defaults to the site wide search limit.
     */
    public ?int $limit = null;

    public function resolvedLimit(): int
    {
        return $this->limit ?? (int) Config::get('settings.sphinxSearchLimit');
    }

    /**
     * The equivalent full text search parameters, used when the Greek hits are turned into
     * the same result structure a Hungarian search produces.
     */
    public function toFullTextSearchParams(): FullTextSearchParams
    {
        $searchParams = new FullTextSearchParams();
        $searchParams->text = $this->text;
        $searchParams->translationId = $this->translationId;
        $searchParams->usxCodes = $this->usxCodes;
        $searchParams->grouping = $this->grouping;
        $searchParams->limit = $this->resolvedLimit();

        return $searchParams;
    }
}
