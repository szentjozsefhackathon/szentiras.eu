<?php

namespace SzentirasHu\Data\Repository;


use SzentirasHu\Data\Entity\Verse;

interface VerseRepository {

    public function getTranslatedChapterVerses($bookId, $chapters);

    /**
     * @return Verse
     */
    public function getLeadVerses($bookId);

    /**
     * @param int[] $verseIds
     * @return Verse[]
     */
    public function getVersesInOrder($verseIds);

    public function getVerses($bookId);

    public function getMaxChapterByBookUsxCode($usxCode, $translationId);
} 