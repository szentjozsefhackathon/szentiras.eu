<?php

namespace SzentirasHu\Http\Controllers\Search;

use App;
use Request;
use Redirect;
use Response;
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
        if (Request::get('textToSearch') == null) {
            return $this->getIndex();
        }
        $form = $this->prepareForm();
        $view = $this->getView($form);
        $view = $this->searchBookRef($form, $view);
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
    private function searchBookRef($form, $view)
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
