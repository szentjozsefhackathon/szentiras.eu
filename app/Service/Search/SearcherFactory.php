<?php
/**

 */

namespace SzentirasHu\Service\Search;

/**
 * Simple factory class to allow creating mock searchers
 * @package SzentirasHu\Service\Search
 */
class SearcherFactory {

    public function createSearcherFor(FullTextSearchParams $params) {
        return new SphinxSearcher($params);
    }

    public function createExtendedSearcherFor(ExtendedSearchParams $params): ExtendedSphinxSearcher
    {
        return new ExtendedSphinxSearcher($params);
    }

} 