<?php

namespace SzentirasHu\Service\Reference;

class VerseRange
{
    /**
     * @var VerseRef
     */
    public ?VerseRef $verseRef;

    /**
     * @var VerseRef
     */
    public ?VerseRef $untilVerseRef;

    public function __construct(?VerseRef $verseRef = null, ?VerseRef $untilVerseRef = null) {
        $this->verseRef = $verseRef;
        $this->untilVerseRef = $untilVerseRef;
    }

    public function toString()
    {
        $s="";
        if ($this->verseRef) {
            $s .= $this->verseRef->toString();
        }
        if ($this->untilVerseRef) {
            if ($this->verseRef) {
                $s .= "-";
            }
            $s .= $this->untilVerseRef->toString();
        }
        return $s;
    }

    public function contains($verse)
    {
        $exact = false;
        if ($this->verseRef) {
            $inside = $verse >= $this->verseRef->verseId && $this->untilVerseRef && $verse <= $this->untilVerseRef->verseId;
            $exact = $verse === $this->verseRef->verseId;
        } else {
            $inside = $this->untilVerseRef && $verse <= $this->untilVerseRef->verseId;
        }
        return $inside || $exact;
    }
}