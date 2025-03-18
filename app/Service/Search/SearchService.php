<?php

/**

 */

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Facades\Config;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Service\Text\TranslationService;

class SearchService
{


    /**
     * @var SearcherFactory
     */
    private $searcherFactory;
    /**
     * @var \SzentirasHu\Data\Repository\VerseRepository
     */
    private $verseRepository;
    /**
     * @var \SzentirasHu\Data\Repository\TranslationRepository
     */
    private $translationRepository;
    /**
     * @var \SzentirasHu\Service\Reference\ReferenceService
     */
    private $referenceService;

    function __construct(SearcherFactory $searcherFactory, VerseRepository $verseRepository, TranslationRepository $translationRepository, ReferenceService $referenceService, protected TranslationService $translationService)
    {
        $this->searcherFactory = $searcherFactory;
        $this->verseRepository = $verseRepository;
        $this->translationRepository = $translationRepository;
        $this->referenceService = $referenceService;
    }

    function getSuggestionsFor($term)
    {
        $result = [];
        $searchParams = new FullTextSearchParams;
        $searchParams->text = $term;
        $searchParams->limit = 40; // increase the limit, as due to grouping there might be more
        $searchParams->grouping = 'verse';
        $searchParams->groupGepi = false; // at the moment I found no way to order the translations, so I need to this an other way
        $searchParams->synonyms = true;
        $sphinxSearcher = $this->searcherFactory->createSearcherFor($searchParams);
        $sphinxResults = $sphinxSearcher->get();
        if ($sphinxResults) {
            $groupedResults = [];

            foreach ($sphinxResults->verses as $verse) {
                $gepi = $verse['gepi'];
                $enabledTranslationArray = Config::get('settings.enabledTranslations');
                if (in_array($verse['trans'], $enabledTranslationArray)) {
                    if (!isset($groupedResults[$gepi])) {
                        $groupedResults[$gepi] = [];
                    }
                    $groupedResults[$gepi][] = $verse;
                }
            }
    
            // sort the verses in each grouped by the order of the translation
            foreach ($groupedResults as $gepi => &$verses) {
                usort($verses, function ($a, $b) {
                    return Translation::getOrderById($a['trans']) <=> Translation::getOrderById($b['trans']);
                });
            }

            // keep only the first verse of each group
            $groupedResults = array_map(function ($verses) {
                $firstElement = reset($verses);
                return $firstElement['id'];
            }, $groupedResults);
            unset($verses);
        
            $verses = $this->verseRepository->getVersesInOrder($groupedResults);

            $texts = [];
            foreach ($verses as $key => $verse) {
                $parsedVerse = $this->getParsedVerse($verse);
                if ($parsedVerse) {
                    $texts[$key] = $parsedVerse;
                }
            }
            $excerpts = $sphinxSearcher->getExcerpts($texts);

            if ($excerpts) {
                foreach ($excerpts as $i => $excerpt) {
                    $verse = $verses[$i];
                    $linkLabel = "{$verse->book->abbrev}&nbsp;{$verse->chapter},{$verse->numv}";
                    $heading = strpos($verse->getType(), 'heading') !== false;
                    $result[] = [
                        'cat' => 'verse',
                        'label' => $excerpt,
                        'link' => "/{$verse->translation->abbrev}/{$verse->book->abbrev} {$verse->chapter},{$verse->numv}" . ($heading ? '?fullContext' : ''),
                        'linkLabel' => $linkLabel,
                        'heading' => $heading
                    ];
                }
            }
            return $result;
        }
    }

    private function getParsedVerse(Verse $verse)
    {
        $verseContainer = new VerseContainer($verse->book);
        $verseContainer->addVerse($verse);
        $parsedVerses = $verseContainer->getParsedVerses();
        if ($parsedVerses[0]->getHeadingText()) {
            return $parsedVerses[0]->getHeadingText();
        } else {
            return $parsedVerses[0]->getText();
        }
    }

    public function getDetailedResults($searchParams)
    {
        $searcher = $this->searcherFactory->createSearcherFor($searchParams);
        $results = $searcher->get();
        if ($results) {
            return $this->handleFullTextResults($results, $searchParams);
        } else {
            return null;
        }
    }

    public function handleFullTextResults(FullTextSearchResult $sphinxResults, FullTextSearchParams $params)
    {
        $allTranslationIds = $this->translationService->getAllTranslations()->pluck('id');
        $sortedVerses = $this->verseRepository->getVersesInOrder($sphinxResults->verseIds);
        $defaultTranslation = $this->translationService->getDefaultTranslation();

        /* beginning of new part */
        $results = [];
        $translations = [];
        foreach ($sphinxResults->verses as $id => $verse) {
            $key = $verse['gepi'];
            switch ($params->grouping) {
                case 'book':
                    $key = substr($key, 0, 3);
                    break;
                case 'chapter':
                    $secondUnderscorePos = strpos($key, '_', strpos($key, '_') + 1);
                    if ($secondUnderscorePos !== false) {
                        $key = substr($key, 0, $secondUnderscorePos);
                    }
                    break;
                default:
            }

            if (!array_key_exists($key, $results)) {
                $results[$key] = ['weights' => [], 'translations' => [$defaultTranslation->abbrev => []]];
            }
            if (!array_key_exists($verse['trans'], $translations)) {
                $translations[$verse['trans']] = $this->translationRepository->getById($verse['trans']);
            }
            $trans = $translations[$verse['trans']];
            // skip if the translation is not enabled
            if ($allTranslationIds->contains($trans->id)) {

                if (!array_key_exists($trans['abbrev'], $results[$key]['translations']) or $results[$key]['translations'][$trans['abbrev']] == array()) {
                    $results[$key]['translations'][$trans['abbrev']] = [
                        'verseIds' => [],
                        'verses' => [],
                        'trans' => $trans,
                        'book' => $sortedVerses[$id]->book
                    ];
                }
                $results[$key]['weights'][] = $verse['weight()'];
                $results[$key]['translations'][$trans['abbrev']]['verseIds'][] = $id;
                $results[$key]['translations'][$trans['abbrev']]['verses'][] = $sortedVerses[$id];
            }
        }

        foreach ($results as $key => $result) {
            rsort($result['weights']);
            $results[$key]['weight'] = reset($result['weights']) + sqrt(array_sum($result['weights'])); // log10()

            foreach ($result['translations'] as $abbrev => $group) {
                if ($group == []) unset($results[$key]['translations'][$abbrev]);
                else {
                    $gepis = array_column($group['verses'], 'gepi');
                    array_multisort($gepis, SORT_ASC, $results[$key]['translations'][$abbrev]['verses']);

                    $currentNumv = false;
                    $currentChapter = false;
                    foreach ($group['verses'] as $k => $verse) {
                        $verseData = [];
                        $verseData['chapter'] = $verse->chapter;
                        $verseData['numv'] = $verse->numv;
                        $verseData['text'] = preg_replace('/<[^>]*>/', ' ', $verse->verse);
                        $verseData['greekText'] = $sphinxResults->verses[$verse->id]['greekText'] ?? null;

                        if ($verse->chapter > $currentChapter) {
                            $verseData['chapterStart'] = true;
                            $currentNumv = $verse->numv;
                        }
                        $currentChapter = $verse->chapter;

                        if ($verse->numv > $currentNumv + 1 and $currentNumv = $verse->numv) {
                            $verseData['ellipseBefore'] = true;
                        }
                        $currentNumv = $verse->numv;

                        $results[$key]['translations'][$abbrev]['verses'][$k] = $verseData;
                    }
                }
            }
        }
        $weights  = array_column($results, 'weight');
        array_multisort($weights, SORT_DESC, $results);
        /* end of new part */

        $resultsByBookNumber = $results;


        /* original */

        $verseContainers = $this->groupVersesByBook($sortedVerses, $params->translationId);

        $results = [];
        $chapterCount = 0;
        $verseCount = 0;
        $gepiToId = []; 
        foreach ($sphinxResults->verses as $id => $verse) {
            $gepiToId[$verse['gepi']] = $id;
        }
        foreach ($verseContainers as $verseContainer) {
            $result = [];
            $result['book'] = $verseContainer->book;
            $result['translation'] = $this->translationRepository->getById($verseContainer->book->translation_id);
            $parsedVerses = $verseContainer->getParsedVerses();
            $result['chapters'] = [];
            foreach ($parsedVerses as $verse) {
                $verseData = [];
                $verseData['chapter'] = $verse->chapter;
                $verseData['numv'] = $verse->numv;
                $verseData['text'] = '';
                $verseData['greekText'] =  $sphinxResults->verses[$gepiToId[$verse->gepi]]['greekText'] ?? null;
                if ($verse->getText()) {
                    $verseData['text'] .= preg_replace('/<[^>]*>/', ' ', $verse->getText());
                }
                $result['chapters'][$verse->chapter][] = $verseData;
                $result['verses'][] = $verseData;
                $verseCount++;
            }
            $chapterCount += count($result['chapters']);
            if ($params->grouping == 'chapter') {
                foreach ($result['chapters'] as $chapterNumber => $verses) {
                    usort($verses, function ($verseData1, $verseData2) {
                        if ($verseData1['numv'] == $verseData2['numv']) {
                            return 0;
                        }
                        return ($verseData1['numv'] < $verseData2['numv']) ? -1 : 1;
                    });
                    $currentNumv = 1;
                    $result['chapters'][$chapterNumber] = $verses;
                    foreach ($verses as $key => $verse) {
                        if ($verse['numv'] > $currentNumv) {
                            $result['chapters'][$chapterNumber][$key]['ellipseBefore'] = true;
                        }
                    }
                }
            }
            if (array_key_exists('verses', $result)) {
                $results[] = $result;
            }
        }
        if ($params->grouping == 'verse') $hitCount = $verseCount;
        else $hitCount = $chapterCount;
        return ['resultsByBookNumber' => $resultsByBookNumber, 'results' => $results, 'hitCount' => $hitCount];
    }

    private function groupVersesByBook($sortedVerses, $translationId)
    {
        $verseContainers = [];
        foreach ($sortedVerses as $verse) {
            $book = $verse->book;
            $key = !$translationId ?
                $book->translation_id . '/' . $book->abbrev :
                $book->abbrev;
            if (!array_key_exists($key, $verseContainers)) {
                $verseContainers[$key] = new VerseContainer($book);
            }
            $verseContainer = $verseContainers[$key];
            $verseContainer->addVerse($verse);
        }
        return $verseContainers;
    }

    public function getSimpleResults($params)
    {
        $searcher = $this->searcherFactory->createSearcherFor($params);
        return $searcher->get();
    }

    /**
     * @param $refToSearch
     * @param $translation
     */
    public function findTranslatedRefs($refToSearch, $translation = null)
    {
        try {
            $ref = CanonicalReference::fromString($refToSearch);
            $storedBookRefs = $this->referenceService->getExistingBookRefs($ref);
            if ($translation === null) {
                $translation = $this->translationService->getDefaultTranslation();
            }
            $translatedBookRefs = [];
            foreach ($storedBookRefs as $storedBookRef) {
                $bookRef = $this->referenceService->translateBookRef($storedBookRef, $translation->id);
                $translatedBookRefs[] = $bookRef;
            }
            return $translatedBookRefs;
        } catch (ParsingException $e) {
        }
    }
}
