<?php
/**

 */

namespace SzentirasHu\Service\Reference;


use Log;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Service\Text\TranslationService;

class ReferenceService
{

    /**
     * @var \SzentirasHu\Data\Repository\TranslationRepository
     */
    private $translationRepository;
    /**
     * @var \SzentirasHu\Data\Repository\BookRepository
     */
    private $bookRepository;
    /**
     * @var \SzentirasHu\Data\Repository\VerseRepository
     */
    private $verseRepository;

    function __construct(TranslationRepository $translationRepository, BookRepository $bookRepository, VerseRepository $verseRepository, protected TranslationService $translationService)
    {
        $this->translationRepository = $translationRepository;
        $this->bookRepository = $bookRepository;
        $this->verseRepository = $verseRepository;
    }

    public function getExistingBookRefs(CanonicalReference $ref, $translationId = false)
    {
        if ($translationId) {
            $translations = [ $this->translationRepository->getById($translationId) ];
        } else {
            $translations = $this->translationRepository->getAll();
        }
        Log::debug("Read {$translations} as translations");
        foreach ($translations as $translation) {
            $storedBookRefs = [];
            foreach ($ref->bookRefs as $bookRef) {
                $storedBookRef = $this->findStoredBookRef($bookRef, $translation);
                if ($storedBookRef) {
                    $storedBookRefs[] = $storedBookRef;
                }
            }
            
            if (!empty($storedBookRefs)) {
                return $storedBookRefs;
            }
        }
        return [];
    }

    private function findStoredBookRef($bookRef, Translation $translation, ?Translation $refTranslation = null)
    {
        $result = null;
        $abbreviatedBook = $this->bookRepository->getByAbbrev($bookRef->bookId, $refTranslation);
        if ($abbreviatedBook) {
            $book = $this->bookRepository->getByUsxCodeForTranslation($abbreviatedBook->usx_code, $translation);
            if ($book) {
                $result = new BookRef($book->abbrev);
                $result->chapterRanges = $bookRef->chapterRanges;
            } else {
            }
        } 
        return $result;
    }

    /**
     *
     * Takes a bookref and get an other bookref according
     * to the given translation.
     *
     * @param ?int $refTranslationId The id of the translation the bookref is interpreted according to.
     * @return BookRef
     */
    public function translateBookRef(BookRef $bookRef, int $translationId, ?int $refTranslationId = null) : BookRef
    {
        $translation = $this->translationRepository->getById($translationId);
        $refTranslation = $this->translationRepository->getById($refTranslationId);
        $result = $this->findStoredBookRef($bookRef, $translation, $refTranslation);
        return $result ? $result : $bookRef;
    }

    public function translateReference(CanonicalReference $ref, $translationId)
    {
        $bookRefs = array_map(function ($bookRef) use ($translationId, $ref) {
            return $this->translateBookRef($bookRef, $translationId, $ref->translationId);
        }, $ref->bookRefs);
        return new CanonicalReference($bookRefs, $translationId);
    }


    public function getCanonicalUrl(CanonicalReference $ref, $translationId)
    {
        $translation = $this->translationRepository->getById($translationId);
        $translatedRef = $this->translateReference($ref, $translationId);
        $url = preg_replace('/[ ]+/', '', "{$translation->abbrev}/{$translatedRef->toString()}");
        return $url;
    }

    public function getSeoUrl(CanonicalReference $ref, $translationId)
    {
        $translation = $this->translationRepository->getById($translationId);
        $translatedRef = $this->translateReference($ref, $translationId);
        $firstBook=$ref->bookRefs[0]->bookId;
        $firstChapter=$ref->bookRefs[0]->chapterRanges[0]->chapterRef->chapterId;
        $url = "{$translation->abbrev}/{$firstBook}{$firstChapter}";
        return $url;
    }


    public function getBook($canonicalReference, $translationId)
    {
        $bookRef = $canonicalReference->bookRefs[0];
        $translation = $this->translationRepository->getById($translationId);
        return $this->bookRepository->getByAbbrevForTranslation($bookRef->bookId, $translation);
    }

    public function getChapterRange($book)
    {
        $bookVerses = $this->verseRepository->getVerses($book->id);
        $fromChapter = $bookVerses->first()->chapter;
        $fromNumv = $bookVerses->first()->numv;
        $toChapter = $bookVerses->last()->chapter;
        $toNumv = $bookVerses->last()->numv;
        return [$fromChapter, $fromNumv, $toChapter, $toNumv];
    }

    public function getPrevNextChapter($canonicalReference, $translationId)
    {
        $book = $this->getBook($canonicalReference, $translationId);
        list($fromChapter, $fromNumv, $toChapter, $toNumv) = $this->getChapterRange($book);
        $bookRef = $canonicalReference->bookRefs[0];
        $chapterId = $bookRef->chapterRanges[0]->chapterRef->chapterId;
        $prevChapter = false;
        $nextChapter = false;
        if ($chapterId > $fromChapter) {
            $prevChapter = $chapterId - 1;
        }
        if ($chapterId < $toChapter) {
            $nextChapter = $chapterId + 1;
        }
        $prevRef = $prevChapter ?
            CanonicalReference::fromString("{$bookRef->bookId} {$prevChapter}", $translationId) :
            false;
        $nextRef = $nextChapter ?
            CanonicalReference::fromString("{$bookRef->bookId} {$nextChapter}", $translationId) :
            false;

        return [$prevRef, $nextRef];
    }

    public function createReferenceFromNumbers($usxCode, $chapterNumber, int $verseNumber, $translation = null) {
        if ($translation == null) {
            $translation = $this->translationService->getDefaultTranslation();
        }
        $book = $this->bookRepository->getByUsxCodeForTranslation($usxCode, $translation->id);
        $ref = CanonicalReference::fromBookChapterVerse($book->abbrev, $chapterNumber, $verseNumber);
        return $ref;
    }

}
