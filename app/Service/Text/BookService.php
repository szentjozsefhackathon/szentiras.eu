<?php

namespace SzentirasHu\Service\Text;

use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\VerseRepository;

class BookService {

    public function __construct(protected BookRepository $bookRepository, protected VerseRepository $verseRepository) {
    }

    /**
     * @return Book[]
     */
    public function getBooksForTranslation(Translation $translation) {
        return $this->bookRepository->getBooksByTranslation($translation->id);
    }

    public function getChapterCount(Book $book, Translation $translation) {
        return $this->verseRepository->getMaxChapterByBookUsxCode($book->usx_code, $translation->id);
    }

}
