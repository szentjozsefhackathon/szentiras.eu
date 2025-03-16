<?php

namespace SzentirasHu\Service\Reference;

class ChapterRef
{
    public int $chapterId;
    public $chapterPart;

    /**
     * @var VerseRange[]
     */
    public $verseRanges = [];

    function __construct($chapterId)
    {
        $this->chapterId = $chapterId;
    }

    public function toString()
    {
        $s = $this->chapterId;
        if ($this->chapterPart) {
            $s .= $this->chapterPart;
        }
        if (count($this->verseRanges) > 0) {
            $s .= ',';
            $last = end($this->verseRanges);
            foreach ($this->verseRanges as $verseRange) {
                $s .= $verseRange->toString();
                if ($last !== $verseRange) {
                    $s .= '.';
                }
            }
        }
        return $s;
    }

    public function addVerseRange(VerseRange $verseRange) {
        $this->verseRanges[] = $verseRange;
    }

}