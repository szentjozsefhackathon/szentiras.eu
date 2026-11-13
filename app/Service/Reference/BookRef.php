<?php

namespace SzentirasHu\Service\Reference;

class BookRef
{
    /** The book's abbrev */
    public $bookId;
    /**
     * @var ChapterRange[]
     */
    public $chapterRanges = [];

    public function __construct($bookId)
    {
        $this->bookId = $bookId;
    }

    public function toString()
    {
        $s = $this->bookId;
        if (count($this->chapterRanges) > 0) {
            $s .= ' ';
            $last = end($this->chapterRanges);
            foreach ($this->chapterRanges as $chapterRange) {
                $s .= $chapterRange->toString();
                if ($last !== $chapterRange) {
                    $s .= ";";
                }
            }
        }
        return $s;
    }

    public function addChapterRange(ChapterRange $chapterRange) {
        $this->chapterRanges[] = $chapterRange;
    }

    /**
     * In a single-chapter book "2Jn 4-9" or "2Jn 4.6" lists verses, so such chapter-only
     * ranges become verse ranges of chapter 1. Explicit verses ("2Jn 1,4-9") and the
     * whole book ("2Jn 1") are left as they are.
     */
    public function convertChaptersToVersesOfSingleChapter(): void
    {
        if (count($this->chapterRanges) === 0) {
            return;
        }
        foreach ($this->chapterRanges as $chapterRange) {
            if (count($chapterRange->chapterRef->verseRanges) > 0
                || ($chapterRange->untilChapterRef !== null && count($chapterRange->untilChapterRef->verseRanges) > 0)) {
                return;
            }
        }
        $firstRange = $this->chapterRanges[0];
        if (count($this->chapterRanges) === 1
            && $firstRange->chapterRef->chapterId === 1
            && !$firstRange->chapterRef->chapterPart
            && $firstRange->untilChapterRef === null) {
            return;
        }
        $chapterRef = new ChapterRef(1);
        foreach ($this->chapterRanges as $chapterRange) {
            $untilVerseRef = $chapterRange->untilChapterRef !== null ? $this->chapterToVerseRef($chapterRange->untilChapterRef) : null;
            $chapterRef->addVerseRange(new VerseRange($this->chapterToVerseRef($chapterRange->chapterRef), $untilVerseRef));
        }
        $this->chapterRanges = [new ChapterRange($chapterRef)];
    }

    private function chapterToVerseRef(ChapterRef $chapterRef): VerseRef
    {
        $verseRef = new VerseRef($chapterRef->chapterId);
        $verseRef->versePart = (string) $chapterRef->chapterPart;

        return $verseRef;
    }

    public function getIncludedChapters() {
        $chapters = [];
        foreach ($this->chapterRanges as $chapterRange) {
            $chapters = array_merge($chapters, $chapterRange->getIncludedChapters());
        }
        return $chapters;
    }
}
