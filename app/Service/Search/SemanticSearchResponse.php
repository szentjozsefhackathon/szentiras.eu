<?php

namespace SzentirasHu\Service\Search;

use Pgvector\Laravel\Distance;

class SemanticSearchResponse {

    /**
     * @param SemanticSearchResult[] $results
     */
    public function __construct(public array $results, public Distance $metric) {

    }

}