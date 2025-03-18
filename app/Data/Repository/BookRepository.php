<?php

namespace SzentirasHu\Data\Repository;


use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;

interface BookRepository {

    public function getBooksByTranslation($translationId);

    /**
     * @param $bookAbbrev
     * @return Book The first book of the given abbrev.
     */
    public function getByAbbrev(string $bookAbbrev, ?Translation $translation) : ?Book;

    /**
     * @param string $abbrev
     * @param Translation $translation
     * @return Book
     */
    public function getByAbbrevForTranslation($abbrev, Translation $translation) : ?Book;

    public function getByUsxCodeForTranslation(string $usxCode, Translation $translation);

}
