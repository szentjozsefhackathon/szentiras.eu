<?php
/**
 */

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePart;
use SzentirasHu\Service\Reference\ChapterRange;

class TextService
{
    /**
     * @var \SzentirasHu\Service\Reference\ReferenceService
     */
    private $referenceService;
    /**
     * @var \SzentirasHu\Data\Repository\BookRepository
     */
    private $bookRepository;
    /**
     * @var \SzentirasHu\Data\Repository\VerseRepository
     */
    private $verseRepository;

    function __construct(ReferenceService $referenceService, BookRepository $bookRepository, VerseRepository $verseRepository)
    {
        $this->referenceService = $referenceService;
        $this->bookRepository = $bookRepository;
        $this->verseRepository = $verseRepository;
    }


    /**
     * @param $canonicalRef
     * @param $translation
     * @return VerseContainer[]
     */
    public function getTranslatedVerses(CanonicalReference $canonicalRef, Translation $translation, $verseTypes = [])
    {
        // replace spaces with underscores
        $cacheKey = "getTranslatedVerses_".base64_encode($canonicalRef->toString())."_".$translation->abbrev;
        // TODO cache if verse types are specified as well
        if (empty($verseTypes) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $translatedRef = $this->referenceService->translateReference($canonicalRef, $translation->id);
        $verseContainers = [];
        foreach ($translatedRef->bookRefs as $bookRef) {
            $book = $this->bookRepository->getByAbbrevForTranslation($bookRef->bookId, $translation);
            if ($book) {
                $verseContainer = new VerseContainer($book, $bookRef);
                if (!empty($bookRef->chapterRanges)) {
                    foreach ($bookRef->chapterRanges as $chapterRange) {
                        $searchedChapters = CanonicalReference::collectChapterIds($chapterRange);
                        $verses = $this->getChapterRangeVerses($chapterRange, $book, $searchedChapters, $verseTypes);
                        foreach ($verses as $verse) {
                            $verseContainer->addVerse($verse);
                        }
                    }    
                } else {
                    $verses = $this->getChapterRangeVerses(null, $book, [], $verseTypes);
                    foreach ($verses as $verse) {
                        $verseContainer->addVerse($verse);
                    }
                }
                $verseContainers[] = $verseContainer;
            }
        }
        if (empty(($verseTypes))) {
            Cache::put($cacheKey, $verseContainers, now()->addHour());
        }
        return $verseContainers;
    }

    public function getChapterRangeVerses(?ChapterRange $chapterRange, Book $book, $searchedChapters, $verseTypes = [])
    {
        $allChapterVerses = $this->verseRepository->getTranslatedChapterVerses($book->id, $searchedChapters, $verseTypes);
        $chapterRangeVerses = [];
        foreach ($allChapterVerses as $verse) {
            if (is_null($chapterRange) || $chapterRange->hasVerse($verse->chapter, $verse->numv)) {
                $chapterRangeVerses[] = $verse;
            }
        }
        return $chapterRangeVerses;
    }

    /**
     * @param $canonicalRef CanonicalReference | string
     * @param Translation $translation
     * @return string
     */
    public function getPureText($canonicalRef, $translation, $includeHeadings = true)
    {
        if (is_string($canonicalRef)) {
            $canonicalRef = CanonicalReference::fromString($canonicalRef);
        }
        $verseContainers = $this->getTranslatedVerses($canonicalRef, $translation);
        $text = '';
        foreach ($verseContainers as $verseContainer) {
            $verses = $verseContainer->getParsedVerses();
            foreach ($verses as $verse) {
                $verseText = $verse->getText($includeHeadings);
                $verseText = preg_replace('/<[^>]*>/', ' ', $verseText);
                $text .= $verseText . ' ';
            }
        }
        return $text;
    }

    public function getPureTextFromNumbers($bookNumber, $chapterNumber, int $verseNumber, $translation) {
        $reference = $this->referenceService->createReferenceFromNumbers($bookNumber, $chapterNumber, $verseNumber, $translation);
        return $this->getPureText($reference, $translation);
    }

    /**
     * @param VerseContainer[] $verseContainers
     * @return string
     */
    public function getTeaser($verseContainers)
    {
        $teaser = "";
        foreach ($verseContainers as $verseContainer) {
            $parsedVerses = $verseContainer->getParsedVerses();
            if (sizeof($parsedVerses) > 0) {
                $teaser .= preg_replace('/<\/?[^>]+>/', ' ', $parsedVerses[0]->getText());
                if ($verseContainer != last($verseContainers) || count($parsedVerses) > 1) {
                    $teaser .= ' ... ';
                }
            }
        }
        return $teaser;
    }

     /**
     * @param VerseContainer[] $verseContainers
     * @return VersePart[]
     */
    public function getHeadings($verseContainers)
    {
        $headings = [];
        foreach ($verseContainers as $verseContainer) {
            $parsedVerses = $verseContainer->getParsedVerses();
            foreach ($parsedVerses as $verseData)
            $headings = array_merge($headings, $verseData->getHeadingVerseParts());            
        }      
        return $headings;
    }

} 
