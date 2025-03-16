<?php

namespace SzentirasHu\Data\Repository;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;

interface TranslationRepository {

    /**
     * @return Collection<Translation>
     */
    public function getAll() : Collection;


    public function getAllOrderedByDenom() : Collection;

    /**
     * @param bool $denom
     */
    public function getByDenom($denom = false) : Collection;

    /**
     * @param Translation $translation
     * @return Book[]
     */
    public function getBooks($translation);

    /**
     * @param $abbrev
     * @return Translation
     */
    public function getByAbbrev($abbrev);

    /**
     * @param $id
     * @return Translation
     */
    public function getById($id);

} 