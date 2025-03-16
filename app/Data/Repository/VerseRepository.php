<?php

namespace SzentirasHu\Data\Repository;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;

interface VerseRepository {

    public function getTranslatedChapterVerses($bookId, $chapters, $types);

    /**
     * @return Collection<Verse>
     */
    public function getLeadVerses($bookId) : Collection;

    /**
     * @param int[] $verseIds
     * @return Verse[]
     */
    public function getVersesInOrder($verseIds);

    public function getVerses($bookId);

    public function getMaxChapterByBookUsxCode($usxCode, $translationId);

    public function getMaxNumv(Book $book, int $chapter, Translation $translation);

} 