<?php
/**

 */

namespace SzentirasHu\Service\Search;


use Config;
use SzentirasHu\Service\Sphinx\SphinxSearch;

class SphinxSearcher implements Searcher
{

    /**
     * @var SphinxSearch
     */
    private $sphinxClient;
    /**
     * @var FullTextSearchParams
     */
    private $params;

    private function addAlternatives($params)
    {
        $text = trim($params->text);
        $originalWords = preg_split('/\W+/u', $text);
        $searchedTerm = " ( @verse \"{$text}\"~".(count($originalWords)+2)." ) ";
        $searchedTerm .= " | ( @verse2 ( {$text} | *{$text}* ) )";        
        $synonyms = [];
        $synonymRepository = \App::make('SzentirasHu\Data\Repository\SynonymRepository');
        $searchedTerm .= ' | ( ';
        foreach ($originalWords as $word) {
            $searchedTerm .= "(@verse3 (";
            $searchedTerm .= "\"{$word}\" | {$word} | *{$word}*";
            $foundSyns = $synonymRepository->findSynonyms($word);
            if ($foundSyns) {
                $synonyms = array_merge($synonyms, $foundSyns->all());
                if (count($synonyms) > 0) {
                    foreach ($synonyms as $syn) {
                        $searchedTerm .= ' | ' . $syn->word;
                    }
                }
            }
            $searchedTerm .= "))";
            if ($word != end($originalWords)) {
                $searchedTerm .= "   ";
            }
        }
        $searchedTerm .= ' )';
        return $searchedTerm;
    }

    public function __construct(FullTextSearchParams $params)
    {
        $term = $this->addAlternatives($params);
        $this->sphinxClient = new SphinxSearch($term);
        \Log::debug('searching', ['params' => $params, 'term' => $term]);
        
        if ($params->limit) {
            $limit = $params->limit;
        } else {
            $limit = (int)Config::get('settings.sphinxSearchLimit');
        }
        $this->sphinxClient->limit($limit);
        
        if ($params->translationId) {
             $this->sphinxClient->filter('trans', $params->translationId);
        }
        if (!empty($params->usxCodes)) {
            $this->sphinxClient->filter('usx_code', array_keys($params->usxCodes));
        }
        if ($params->groupGepi) {
            $this->sphinxClient->groupGepi(true);
        }
        if ($params->countOnly) {
            $this->sphinxClient->countOnly(true);
        }
        $this->params = $params;
    }

    public function getExcerpts($verses)
    {
        return $this->sphinxClient->buildExcerpts($verses, trim($this->params->text));
    }

    public function get()
    {
        $sphinxResult = $this->sphinxClient->get();
        if ($sphinxResult) {
            $fullTextSearchResult = new FullTextSearchResult();
            if (array_has($sphinxResult[0], "count(*)")) {
                $fullTextSearchResult->hitCount = $sphinxResult[0]["count(*)"];
            } else {
                $fullTextSearchResult->verseIds = array_map(fn ($elem) => $elem['id'], $sphinxResult);
                // transform sphinxResult to id => element
                $fullTextSearchResult->verses = array_combine($fullTextSearchResult->verseIds, $sphinxResult);
                $fullTextSearchResult->hitCount = count($sphinxResult);
            }
            return $fullTextSearchResult;
        } else {
            return null;
        }
    }

}