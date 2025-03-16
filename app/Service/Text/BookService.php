<?php

namespace SzentirasHu\Service\Text;

use Cache;
use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\VerseRepository;

class BookService {

    public function __construct(protected BookRepository $bookRepository, protected VerseRepository $verseRepository, protected TranslationService $translationService) {   
    }

    public function getBooksForTranslation(Translation $translation) : Collection {
        return $this->bookRepository->getBooksByTranslation($translation->id);
    }

    public function getChapterCount(Book $book, Translation $translation) {
        return $this->verseRepository->getMaxChapterByBookUsxCode($book->usx_code, $translation->id);
    }

    public function getVerseCount(Book $book, int $chapter, Translation $translation) {
        return $this->verseRepository->getMaxNumv($book, $chapter, $translation);
    }

    public function getBookByUsxCodeTranslation(string $usxCode, string $translationAbbrev) : Book {
        return Cache::remember("getBookByUsxCodeTranslation_{$usxCode}_{$translationAbbrev}", now()->addDays(), function() use ($usxCode, $translationAbbrev) {
        $translation = $this->translationService->getByAbbreviation($translationAbbrev);
        return $this->bookRepository->getByUsxCodeForTranslation($usxCode, $translation);
        });
    }

}
