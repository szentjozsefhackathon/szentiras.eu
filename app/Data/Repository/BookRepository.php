<?php

namespace SzentirasHu\Data\Repository;


use SzentirasHu\Data\Entity\Book;

interface BookRepository {

    public function getBooksByTranslation($translationId);

    /**
     * @param $bookAbbrev
     * @return Book The first book of the given abbrev.
     */
    public function getByAbbrev($bookAbbrev, $translationId = null);

    /**
     * @param string $abbrev
     * @param int $translationId
     * @return Book
     */
    public function getByAbbrevForTranslation($abbrev, $translationId);

    public function getByOrderForTranslation($order, $translationId);

} 