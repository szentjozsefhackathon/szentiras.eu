<?php
/**

 */

namespace SzentirasHu\Service\Search;


use Config;
use Sphinx\SphinxClient;
use SphinxSearch;

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
        //echo ">>".$searchedTerm."<<<br/>";
        return $searchedTerm;
    }

    public function __construct(FullTextSearchParams $params)
    {
        $term = $this->addAlternatives($params);
        $this->sphinxClient = SphinxSearch::search($term);
        \Log::debug('searching', ['params' => $params, 'term' => $term]);
        $this->sphinxClient->setFieldWeights(['verse'=>100,'verse2'=>10,'verse3'=>1]);
        $this->sphinxClient->setIndexWeights(['verse'=>2,'verse_root'=>1]);
        $this->sphinxClient->setMatchMode(SphinxClient::SPH_MATCH_EXTENDED);
        $this->sphinxClient->setSortMode(SphinxClient::SPH_SORT_EXTENDED, "@weight DESC, gepi ASC");
        
        if ($params->limit) {
            $limit = $params->limit;
        } else {
            $limit = (int)Config::get('settings.sphinxSearchLimit') + 1;
        }
        $this->sphinxClient->limit($limit);
        
        /*
         * Itt if($params->groupByVerse ) volt, de az mindig false volt. Viszont, ha valaha true, akkor nem működik valami.
        if ($params->grouping == 'verse') {        
            $this->sphinxClient->setGroupBy('gepi', SphinxClient::SPH_GROUPBY_ATTR, '@relevance desc');
        }
         */
        if ($params->translationId) {
            $this->sphinxClient->filter('trans', $params->translationId);
        }
        if ($params->bookNumbers !== null && count($params->bookNumbers) > 0) {
            $this->sphinxClient->filter('book_number', $params->bookNumbers);
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
            //echo "<pre>".print_R($sphinxResult['matches'],1)."</pre>";       
            $fullTextSearchResult = new FullTextSearchResult();
            $fullTextSearchResult->verseIds = array_keys($sphinxResult['matches']);
            $fullTextSearchResult->verses = $sphinxResult['matches'];
            $fullTextSearchResult->hitCount = count($sphinxResult['matches']);

            return $fullTextSearchResult;
        } else {
            return null;
        }
    }

}