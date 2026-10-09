<?php
/**
 */

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Cdn\CacheTagCollector;
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

    function __construct(ReferenceService $referenceService, BookRepository $bookRepository, VerseRepository $verseRepository, protected CacheTagCollector $cacheTagCollector)
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
        $cacheKey = $this->getTranslatedVersesCacheKey($canonicalRef, $translation);
        // TODO cache if verse types are specified as well
        if (empty($verseTypes) && Cache::has($cacheKey)) {
            return $this->tagVerseContainers(Cache::get($cacheKey), $translation);
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
            Cache::forever($cacheKey, $verseContainers);
        }
        return $this->tagVerseContainers($verseContainers, $translation);
    }

    /**
     * Record the shown books as CDN cache tags of the current response.
     *
     * @param VerseContainer[] $verseContainers
     * @return VerseContainer[]
     */
    private function tagVerseContainers(array $verseContainers, Translation $translation): array
    {
        foreach ($verseContainers as $verseContainer) {
            $this->cacheTagCollector->add(CacheTagCollector::textTag($translation->abbrev, (string) $verseContainer->book->usx_code));
        }
        return $verseContainers;
    }

    /**
     * The key includes the translation's text version, so bumping the version
     * invalidates every cached reference of that translation at once.
     */
    public function getTranslatedVersesCacheKey(CanonicalReference $canonicalRef, Translation $translation): string
    {
        $textVersion = Cache::rememberForever("textVersion_{$translation->id}", fn (): string => '0');

        return "getTranslatedVerses_{$textVersion}_".base64_encode($canonicalRef->toString())."_".$translation->abbrev;
    }

    /**
     * Drop the cached texts of one translation after its stored verses changed, leaving the rest of the cache intact.
     */
    public function invalidateTranslationTexts(Translation $translation): void
    {
        Cache::forever("textVersion_{$translation->id}", uniqid('', true));
        Cache::forget("bookHeaders_{$translation->abbrev}");
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
     * @param string|bool|null $headingType One of: null (default = plain), 'plain', 'markdown', 'none'
     * @return string
     */
    public function getPureText($canonicalRef, $translation, $headingType = null)
    {
        // Legacy boolean support
        if (is_bool($headingType)) {
            $headingType = $headingType ? 'plain' : 'none';
        }
        // Normalize: null -> plain
        if ($headingType === null) {
            $headingType = 'plain';
        }
        
        if (is_string($canonicalRef)) {
            $canonicalRef = CanonicalReference::fromString($canonicalRef);
        }
        $verseContainers = $this->getTranslatedVerses($canonicalRef, $translation);
        $text = '';
        foreach ($verseContainers as $verseContainer) {
            $verses = $verseContainer->getParsedVerses();
            foreach ($verses as $verse) {
                $verseText = $verse->getText($headingType);
                $verseText = preg_replace('/<[^>]*>/', ' ', $verseText);
                $text .= $verseText . ' ';
            }
        }
        return trim($text);
    }

    public function getPureTextFromNumbers($bookNumber, $chapterNumber, int $verseNumber, $translation) {
        $reference = $this->referenceService->createReferenceFromNumbers($bookNumber, $chapterNumber, $verseNumber, $translation);
        return $this->getPureText($reference, $translation);
    }

    /**
     * Builds a plain-text teaser used as the meta/OpenGraph description.
     *
     * Verses are concatenated (HTML tags stripped, whitespace collapsed) until
     * the text reaches $maxLength characters, so the description is long enough
     * to be useful for search engines instead of a single short verse.
     *
     * @param VerseContainer[] $verseContainers
     */
    public function getTeaser($verseContainers, int $maxLength = 320): string
    {
        $teaser = "";
        foreach ($verseContainers as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $parsedVerse) {
                $verseText = trim(preg_replace('/\s+/', ' ', preg_replace('/<\/?[^>]+>/', ' ', $parsedVerse->getText())));
                if ($verseText === "") {
                    continue;
                }
                $teaser = $teaser === "" ? $verseText : "{$teaser} {$verseText}";
                if (mb_strlen($teaser) >= $maxLength) {
                    return $teaser;
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
