<?php

namespace SzentirasHu\Service\Search;

class ExtendedSearchResult
{
    /**
     * @param  array<int, ExtendedSearchHit>  $hits  the best weighted first
     * @param  int  $totalHitCount  the number of hits regardless of the limit
     * @param  int  $queryWordCount  the distinct words searched for, the frequent ones left out; the quorum and a hit's word count count these
     * @param  int  $questionWordCount  all the words of the search, the most a hit's longest run can be
     */
    public function __construct(
        public array $hits,
        public int $totalHitCount,
        public int $queryWordCount = 0,
        public int $questionWordCount = 0,
    ) {}
}
