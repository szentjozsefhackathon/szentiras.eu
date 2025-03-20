<?php

namespace SzentirasHu\Http\Controllers\Search;

use App;
use Request;
use Redirect;
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

    public function suggestGreek() {
        $term = Request::get('term');
        $previousWords = "";
        if (str_contains($term, " ")) {
            $previousWords = substr($term, 0, strrpos($term, " ")) . " ";
        }
        $word = str_replace($previousWords, "", strtolower($term));
        $query = GreekVerse::query()->limit(20);
        $query->where('strong_normalizations', '~', "{$word}");
        $foundWords = [];
        // for each greek verse find the words in the strong_normalizations field which match $word
        foreach ($query->get()->toArray() as $greekVerse) {
            $normalizedWords = explode(" ", $greekVerse['strong_normalizations']);
            $strongWords = explode(" ", $greekVerse['strongs']);
            $transliteratedWords = explode(" ", $greekVerse['strong_transliterations']);
            foreach ($normalizedWords as $i => $normalizedWord) {
                if (str_contains($normalizedWord, $word)) {
                    $foundWords[$normalizedWord] = ["value" => $previousWords . $normalizedWord, "label" => "{$strongWords[$i]} ({$transliteratedWords[$i]})"];
                }
            }
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
                'cat' => 'ref',
                'label' => $concatenatedLabel,
                'link' => "/{$concatenatedLabel}"
            ];
        }
        $suggestions = $this->searchService->getSuggestionsFor($term);
        if (is_array($suggestions)) {
            $result = array_merge($result, $suggestions);
        }
        return Response::json($result);
    }

    public function anySearch()
    {
        if (Request::get('textToSearch') == null && Request::get('greekTranslit') == null) {
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

    /**
     * @return SearchForm
     */
    private function prepareForm()
    {
        $form = new SearchForm();
        $form->textToSearch = Request::get('textToSearch');
        $form->greekTranslit = Request::get('greekTranslit');
        $form->grouping = Request::get('grouping');
        $form->book = Request::get('book');
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
        $greekVerses = [];
        if ($form->greekTranslit) {
            $explodedGreekText = explode(" " , strtolower($form->greekTranslit));
            $query = GreekVerse::query();
            foreach ($explodedGreekText as $i => $word) {
                $query->where('strong_normalizations', '~', "{$word}");
            }
            $greekVerses = $query->get()->toArray();
            $gepis = array_map(fn ($greekVerse) => "{$greekVerse['usx_code']}_{$greekVerse['chapter']}_{$greekVerse['verse']}", $greekVerses);
            $greekVersesPerGepi = array_combine($gepis, $greekVerses);
            $searchParams = $this->createFullTextSearchParams($form);
            $query = Verse::query();
            if ($searchParams->translationId) {
                $query = $query->where('trans', $searchParams->translationId);
            }
            if ($searchParams->usxCodes) {
                $query= $query->whereIn('usx_code', array_keys($searchParams->usxCodes));
            }
            $query= $query->whereIn('gepi', $gepis)->whereIn('tip', [901]);

            $verses = $query->limit(5000)->orderBy('tip')->orderBy('usx_code')->orderBy('chapter')->orderBy('numv')->get();
            
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
            }
            if (!$verses->isEmpty()) {
                $results->hitCount = count($verses);
                $processedResults = $this->searchService->handleFullTextResults($results, $searchParams);
                $view = $view->with('fullTextResults', $processedResults);
            }

        } else {
            $searchParams = $this->createFullTextSearchParams($form);
            $view = $this->addTranslationHits($view, $searchParams);
            $results = $this->searchService->getDetailedResults($searchParams);
            if ($results) {
                $view = $view->with('fullTextResults', $results);
            }    
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
        $view = $view->with('translationHits', $translationHits);
        return $view;
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
