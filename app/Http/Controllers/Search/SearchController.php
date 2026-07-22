<?php

namespace SzentirasHu\Http\Controllers\Search;

use App;
use Request;
use Redirect;
use Redis;
use Response;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\FullTextSearchParams;
use SzentirasHu\Service\Search\GreekSearchMode;
use SzentirasHu\Service\Search\GreekSearchParams;
use SzentirasHu\Service\Search\GreekSearchRule;
use SzentirasHu\Service\Search\GreekSearchService;
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

    function __construct(BookRepository $bookRepository, TranslationRepository $translationRepository, TextService $textService, SearchService $searchService, protected TranslationService $translationService, protected ReferenceService $referenceService, protected GreekSearchService $greekSearchService)
    {
        $this->bookRepository = $bookRepository;
        $this->translationRepository = $translationRepository;
        $this->textService = $textService;
        $this->searchService = $searchService;
    }

    public function getIndex()
    {
        return $this->getView($this->prepareForm())->with('greekSearch', Request::boolean('greek'));
    }

    public function anySuggest()
    {
        $result = [];
        $term = Request::input('term');
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
        $searchParams =  new FullTextSearchParams();
        $searchParams->text = $term;
        $searchParams->translationId = (int)(request()->input('translation') ?? null);
        $book = request()->input('book');
        if ($book) {
            $searchParams->usxCodes = $this->extractBookUsxCodes($book);
        }

        $suggestions = $this->searchService->getSuggestionsFor($searchParams);
        if (!empty($suggestions)) {
            $translationHits = $this->retrieveTranslationHits($searchParams);
            $hitCount = array_sum(array_pluck($translationHits, 'hitCount'));
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

    public function suggestStrong()
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
        foreach ($normalizations as $strongWord) {
            $meanings = $strongWord->dictionaryMeanings->pluck('meaning')->join(', ');
            $foundWords[$strongWord->normalized] = ["value" => $previousWords . $strongWord->normalized, "label" => "{$strongWord->lemma} ($strongWord->transliteration - {$meanings})"];
        }
        ksort($foundWords);
        return Response::json(array_values($foundWords));
    }


    public function suggestGreek()
    {
        $term = Request::input('term');
        $searchParams =  new FullTextSearchParams();
        $searchParams->text = "{$term}";

        $book = request()->input('book');
        if ($book) {
            $searchParams->usxCodes = $this->extractBookUsxCodes($book);
        }
        $sphinxClient = new SphinxSearch($searchParams->text);
        if (!empty($searchParams->usxCodes)) {
            $sphinxClient->filter('usx_code', array_keys($searchParams->usxCodes));
        }
        $limit = 10;
        $sphinxClient->limit($limit);
        $sphinxResult = $sphinxClient->getGreekNormalizations();
        $sphinxClient = new SphinxSearch($searchParams->text);
        if (!empty($searchParams->usxCodes)) {
            $sphinxClient->filter('usx_code', array_keys($searchParams->usxCodes));
        }
        $sphinxClient->countOnly(true);
        $countResult = $sphinxClient->getGreekNormalizations();
        $items = [];
        if ($sphinxResult) {
            foreach ($sphinxResult as $result) {
                $verse = GreekVerse::whereId($result['id'])->first();
                $text = str_replace('¶', '', $verse->text);
                $positions = $this->findWordPositions($term, $verse->normalization);
                $textWords = explode(' ', $text);
                $textWords = array_values(array_filter($textWords));
                foreach ($positions as $position) {
                    if (array_key_exists($position, $textWords)) {
                        $textWords[$position] = "<b>" . $textWords[$position] . "</b>";
                    }
                }
                $text = implode(' ', $textWords);
                $ref = $this->referenceService->createReferenceFromNumbers($verse->usx_code, $verse->chapter, $verse->verse);
                $items[] = ['label' => $text, 'link' => "/{$ref->toString()}", 'linkLabel' => $ref->toString(), 'value' => $verse->id];
            }
            $items[0]['hitCount'] = $countResult[0]['hitcount'];
        }
        return Response::json($items);
    }

    private function findWordPositions($searchTerm, $normalizedText)
    {
        $positions = [];
        $words = array_filter(explode(' ', strtolower($searchTerm)));
        $normalizedText = array_values(array_filter(explode(' ', $normalizedText)));
        foreach ($normalizedText as $i => $normalizedWord) {
            foreach ($words as $word) {
                $pattern = str_ends_with($word, "*") ? '/^' . preg_quote(substr($word, 0, -1), '/') . '/i' : '/\b' . preg_quote($word, '/') . '\b/i';
                if (preg_match($pattern, preg_replace('/[^\w]/', '', $normalizedWord))) {
                    $positions[] = $i;
                }
            }
        }
        return $positions;
    }


    public function greekSearch()
    {
        if (Request::get('greekTranslit') == null && Request::get('greekText') == null) {
            return $this->getIndex();
        }
        $form = $this->prepareForm();
        $view = $this->getView($form);
        $results = $this->greekSearchService->search($this->createGreekSearchParams($form));
        if ($results) {
            $view = $view->with('fullTextResults', $results);
        }
        return $view->with('greekSearch', true);
    }

    private function createGreekSearchParams(SearchForm $form): GreekSearchParams
    {
        $searchParams = new GreekSearchParams();
        $searchParams->mode = GreekSearchMode::tryFrom((string) $form->mode) ?? GreekSearchMode::Lemma;
        $searchParams->text = (string) ($searchParams->mode === GreekSearchMode::Lemma ? $form->greekTranslit : $form->greekText);
        $searchParams->rule = GreekSearchRule::tryFrom((string) $form->rule) ?? GreekSearchRule::All;
        $searchParams->translationId = $form->translation ? $form->translation->id : null;
        $searchParams->usxCodes = $this->extractBookUsxCodes($form->book);
        $searchParams->grouping = $form->grouping;

        return $searchParams;
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
        $form->rule = Request::get('rule');
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
            'books' => $books,
            'teaser' => 'Keresés a teljes Szentírásban – Biblia keresés magyarul és görögül, több fordításban egyszerre.',
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

    private function retrieveTranslationHits($searchParams)
    {
        $translationHits = [];
        foreach ($this->translationRepository->getAll() as $translation) {
            if ($searchParams->translationId && $searchParams->translationId != $translation->id) {
                continue;
            }
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
