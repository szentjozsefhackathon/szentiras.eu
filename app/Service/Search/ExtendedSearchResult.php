<?php

namespace SzentirasHu\Service\Search;

class ExtendedSearchResult
{
    /**
     * @param  array<int, ExtendedSearchHit>  $hits  the best weighted first
     * @param  int  $totalHitCount  the number of hits regardless of the limit
     * @param  int  $queryWordCount  the distinct words searched for, the frequent ones left out; the quorum and a hit's word count count these
     * @param  int  $questionWordCount  all the words of the search, the most a hit's longest run can be
     * @param  array<int, ExtendedSearchHeadingHit>  $headings  the headings found, apart from the verses, the best weighted first
     * @param  int  $headingHitCount  the number of headings found regardless of the limit
     */
    public function __construct(
        public array $hits,
        public int $totalHitCount,
        public int $queryWordCount = 0,
        public int $questionWordCount = 0,
        public array $headings = [],
        public int $headingHitCount = 0,
    ) {}
}
