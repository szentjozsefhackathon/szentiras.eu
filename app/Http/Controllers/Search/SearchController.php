<?php

namespace SzentirasHu\Http\Controllers\Search;

use App;
use Request;
use Redirect;
use Redis;
use Response;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\FullTextSearchParams;
use SzentirasHu\Service\Search\FullTextSearchResult;
use SzentirasHu\Service\Search\SearchService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Sphinx\SphinxSearch;
use SzentirasHu\Service\Text\TranslationService;
use View;

/**
 * Controller for searching. Based on REST conventions.
 *
 * @author berti
 */
class SearchController extends Controller
{

    /**
     * @var BookRepository
     */
    private $bookRepository;

    /**
     * @var TranslationRepository
     */
    private $translationRepository;
    /**
     * @var \SzentirasHu\Service\Text\TextService
     */
    private $textService;
    /**
     * @var \SzentirasHu\Service\Search\SearchService
     */
    private $searchService;

    function __construct(BookRepository $bookRepository, TranslationRepository $translationRepository, TextService $textService, SearchService $searchService, protected TranslationService $translationService)
    {
        $this->bookRepository = $bookRepository;
        $this->translationRepository = $translationRepository;
        $this->textService = $textService;
        $this->searchService = $searchService;
    }

    public function getIndex()
    {
        return $this->getView($this->prepareForm());
    }

    public function suggestGreek()
    {
        $term = Request::get('term');
        $previousWords = "";
        if (str_contains($term, " ")) {
            $previousWords = mb_substr($term, 0, strrpos($term, " ")) . " ";
        }
        $word = mb_strtolower(str_replace($previousWords, "", $term));
        $query = StrongWord::query()->has('greekVerses')->with('dictionaryMeanings');
        $query->where('normalized', '~', "{$word}")
            ->orWhereHas('dictionaryMeanings', function ($query) use ($word) {
                $query->where('meaning', 'ILIKE', "%{$word}%");
            });
        $normalizations = $query->limit(20)->get();
        $foundWords = [];
        // for each greek verse find the words in the strong_normalizations field which match $word
        foreach ($normalizations as $strongWord) {
            $meanings = $strongWord->dictionaryMeanings->pluck('meaning')->join(', ');
            $foundWords[$strongWord->normalized] = ["value" => $previousWords . $strongWord->normalized, "label" => "{$strongWord->lemma} ($strongWord->transliteration - {$meanings})"];
        }
        ksort($foundWords);
        return Response::json(array_values($foundWords));
    }

    public function anySuggest()
    {
        $result = [];
        $term = Request::get('term');
        $refs = $this->searchService->findTranslatedRefs($term);
        if (!empty($refs)) {
            $labels = [];
            foreach ($refs as $ref) {
                $labels[] = $ref->toString();
            }
            $concatenatedLabel = implode(';', $labels);
            $result[] = [
                'hitCount' => 1,
                'cat' => 'ref',
                'label' => $concatenatedLabel,
                'link' => "/{$concatenatedLabel}"
            ];
        }
        $searchParamsForHit =  new FullTextSearchParams();
        $searchParamsForHit->text = $term;
        $suggestions = $this->searchService->getSuggestionsFor($term);        
        if (!empty($suggestions)) {
            $translationHits = $this->retrieveTranslationHits($searchParamsForHit);
            $hitCount = max(array_pluck($translationHits, 'hitCount'));            
            $result = array_merge($result, $suggestions);
            $result[0]['hitCount'] = $hitCount;
        }
        return Response::json($result);
    }

    public function anySearch()
    {
        if (Request::get('textToSearch') == null) {
            return $this->getIndex();
        }
        $form = $this->prepareForm();
        $view = $this->getView($form);
        if ($form->textToSearch) {
            $view = $this->searchBookRef($form, $view);
        }
        $view = $this->searchFullText($form, $view);
        return $view;
    }

    public function greekSearch()
    {
        if (Request::get('greekTranslit') == null && Request::get('greekText') == null) {
            return $this->getIndex();
        }
        $form = $this->prepareForm();
        $view = $this->getView($form);
        $searchParams = $this->createFullTextSearchParams($form);
        $gepis = [];
        $greekVersesPerGepi = [];
        if ($form->mode == 'lemma') {
            $greekVerses = [];
            $explodedGreekText = explode(" ", strtolower($form->greekTranslit));
            $query = GreekVerse::query();
            foreach ($explodedGreekText as $i => $word) {
                $query->where('strong_normalizations', '~*', "\\y{$word}\\y");
            }
            $greekVerses = $query->get()->toArray();
            $gepis = array_map(fn($greekVerse) => "{$greekVerse['usx_code']}_{$greekVerse['chapter']}_{$greekVerse['verse']}", $greekVerses);
            $greekVersesPerGepi = array_combine($gepis, $greekVerses);
        } else if ($form->mode == 'verse') {
            // use SphinxSearch to find the greek verses
            $sphinxClient = new SphinxSearch(implode(' ', explode(' ', $form->greekText)));
            $limit = 1000;
            $sphinxClient->limit($limit);

            if (!empty($searchParams->usxCodes)) {
                $sphinxClient->filter('usx_code', array_keys($searchParams->usxCodes));
            }
            if ($searchParams->groupGepi) {
                $sphinxClient->groupGepi(true);
            }
            if ($searchParams->countOnly) {
                $sphinxClient->countOnly(true);
            }
            $sphinxResult = $sphinxClient->getGreekNormalizations();
            if ($sphinxResult) {
                $fullTextSearchResult = new FullTextSearchResult();
                if (array_key_exists("count(*)", $sphinxResult[0])) {
                    $fullTextSearchResult->hitCount = $sphinxResult[0]["count(*)"];
                } else {
                    $fullTextSearchResult->verseIds = array_map(fn($elem) => $elem['id'], $sphinxResult);
                    // transform sphinxResult to id => element
                    $fullTextSearchResult->verses = array_combine($fullTextSearchResult->verseIds, $sphinxResult);
                    $fullTextSearchResult->hitCount = count($sphinxResult);
                }
                $greekVerses = GreekVerse::whereIn('id', $fullTextSearchResult->verseIds)->get();
                $gepis = $greekVerses->pluck('gepi')->toArray();
                $greekVersesPerGepi = array_combine($gepis, $greekVerses->toArray());
            }
        }

        if (!empty($gepis)) {
            $query = Verse::query();
            if ($searchParams->translationId) {
                $query = $query->where('trans', $searchParams->translationId);
            }
            if ($searchParams->usxCodes) {
                $query = $query->whereIn('usx_code', array_keys($searchParams->usxCodes));
            }
            $query = $query->whereIn('gepi', $gepis)->whereIn('tip', [901]);

            $verses = $query->limit(1000)->orderBy('tip')->orderBy('usx_code')->orderBy('chapter')->orderBy('numv')->get();

            $results = new FullTextSearchResult();
            $results->verses = [];
            foreach ($verses as $verse) {
                $results->verseIds[] = $verse->id;
                $results->verses[$verse->id]['id'] = $verse->id;
                $results->verses[$verse->id]['trans'] = $verse->trans;
                $results->verses[$verse->id]['usx_code'] = $verse->usx_code;
                $results->verses[$verse->id]['chapter'] = $verse->chapter;
                $results->verses[$verse->id]['numv'] = $verse->numv;
                $results->verses[$verse->id]['gepi'] = $verse->gepi;
                $results->verses[$verse->id]['tip'] = $verse->tip;
                $results->verses[$verse->id]['weight()'] = 1;
                $results->verses[$verse->id]['greekText'] = str_replace('¶', '', $greekVersesPerGepi[$verse->gepi]['text']);
                $results->verses[$verse->id]['greekTransliteration'] = $greekVersesPerGepi[$verse->gepi]['transliteration'];
            }
            if (!$verses->isEmpty()) {
                $results->hitCount = count($verses);
                $processedResults = $this->searchService->handleFullTextResults($results, $searchParams);
                $view = $view->with('fullTextResults', $processedResults);
            }
        }

        $view = $view->with('greekSearch', true);

        return $view;
    }


    /**
     * @return SearchForm
     */
    private function prepareForm()
    {
        $form = new SearchForm();
        $form->textToSearch = Request::get('textToSearch');
        $form->greekTranslit = Request::get('greekTranslit');
        $form->greekText = Request::get('greekText');
        $form->grouping = Request::get('grouping');
        $form->book = Request::get('book');
        $form->mode = Request::get('mode');
        $form->limit = Request::get('limit') ?? 100;
        $form->offset = Request::get('offset') ?? 0;
        if (Request::get('translation') > 0) {
            $form->translation = $this->translationRepository->getById(Request::get('translation'));
        }
        return $form;
    }

    private function getView($form)
    {
        $translations = $this->translationRepository->getAll();
        $books = $this->bookRepository->getBooksByTranslation($this->translationService->getDefaultTranslation()->id);
        return View::make("search.search", [
            'form' => $form,
            'translations' => $translations,
            'books' => $books
        ]);
    }

    /**
     * @param $form
     * @param $view
     * @return mixed
     */
    private function searchBookRef(SearchForm $form, $view)
    {
        $augmentedView = $view;
        $translatedRefs = $this->searchService->findTranslatedRefs($form->textToSearch, $form->translation);
        if (!empty($translatedRefs)) {
            $translation = $form->translation ? $form->translation : $this->translationService->getDefaultTranslation();
            $verseContainers = $this->textService->getTranslatedVerses(CanonicalReference::fromString($form->textToSearch), $translation);
            $labels = [];
            foreach ($translatedRefs as $ref) {
                $labels[] = $ref->toString();
            }
            $concatenatedLabel = implode(';', $labels);
            if ($verseContainers) {
                $augmentedView = $view->with('bookRef', [
                    'label' => $concatenatedLabel,
                    'link' => "/{$translation->abbrev}/{$concatenatedLabel}",
                    'verseContainers' => $verseContainers
                ]);
            }
        }
        return $augmentedView;
    }

    /**
     * @param SearchForm $form
     * @param $view
     * @return mixed
     */
    private function searchFullText($form, $view)
    {
        $searchParams = $this->createFullTextSearchParams($form);
        $view = $this->addTranslationHits($view, $searchParams);
        $results = $this->searchService->getDetailedResults($searchParams);
        if ($results) {
            $view = $view->with('fullTextResults', $results);
        }
        return $view;
    }

    /**
     * @param $book
     * @return array The keys will contain the USX codes of the books to search in.
     */
    public static function extractBookUsxCodes(?string $book)
    {
        $bookUsxCodes = [];
        if ($book == 'old_testament') {
            $bookUsxCodes = UsxCodes::OLD_TESTAMENT;
        } else if ($book == 'new_testament') {
            $bookUsxCodes = UsxCodes::NEW_TESTAMENT;
        } else if ($book == 'all') {
            $bookUsxCodes = [];
        } else if ($book !== null) {
            $bookUsxCodes = [$book => true];
        }
        return $bookUsxCodes;
    }

    /**
     * @param $view
     * @param $searchParams
     * @return mixed
     */
    private function addTranslationHits($view, $searchParams)
    {
        $view = $view->with('translationHits', $this->retrieveTranslationHits($searchParams));
        return $view;
    }

    private function retrieveTranslationHits($searchParams) {
        $translationHits = [];
        foreach ($this->translationRepository->getAll() as $translation) {
            $params = clone $searchParams;
            $params->countOnly = true;
            $params->translationId = $translation->id;
            $searchHits = $this->searchService->getSimpleResults($params);
            if ($searchHits) {
                $translationHits[] = ['translation' => $translation, 'hitCount' => $searchHits->hitCount];
            }
        }
        return $translationHits;
    }

    /**
     * @param $form
     * @return FullTextSearchParams
     */
    private function createFullTextSearchParams($form)
    {
        $searchParams = new FullTextSearchParams;
        $searchParams->text = $form->textToSearch;
        if ($form->translation) {
            $searchParams->translationId = $form->translation->id;
        }
        $searchParams->usxCodes = $this->extractBookUsxCodes($form->book);
        $searchParams->synonyms = true;
        $searchParams->grouping = $form->grouping;
        return $searchParams;
    }

    /**
     * Search from old page, searchbible.php, texttosearch comes as post param
     */
    public function postLegacy()
    {
        $textToSearch = Request::get('texttosearch');
        return Redirect::to("/kereses/search?textToSearch={$textToSearch}");
    }
}
