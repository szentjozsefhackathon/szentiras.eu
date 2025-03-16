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

    public function getIncludedChapters() {
        $chapters = [];
        foreach ($this->chapterRanges as $chapterRange) {
            $chapters = array_merge($chapters, $chapterRange->getIncludedChapters());
        }
        return $chapters;
    }
}
