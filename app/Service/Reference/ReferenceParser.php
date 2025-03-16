<?php

namespace SzentirasHu\Service\Reference;

use Throwable;

/**
 * Examples of possible formats:
 * - 1Kor - full book
 * - 1Kor 13 - a full chapter
 * - 1Kor 13,1 - a verse
 * - 1Kor 13,1-10 - a verse range
 * - 1Kor 13,2-14,3 - crosschapter ranges
 * - 1Kor 13,1.2-10.24-30 - multiple ranges
 * - 1Kor 13,1a-3.5b-14 - multiple ranges with verse parts
 * - 1Kor 13;25 - multiple chapters
 * - 1Kor 13,1a-3.5b-14|14,23.24-26 - multiple chapters
 * - 1Kor; Jn; 2Fil - multiple books
 * - 1Kor 13,1a-3.5b-14.6a|14,23-15,22; Jn 14,22-39 - everything combined
 * So generally we have the following grammar here:
 * CanonicalReference = BookReference ("; " BookReference)*
 * BookReference = BookId (" "? ChapterReference)
 * ChapterReference = ChapterId ("," VerseReference)? (";" ChapterReference)*
 * VerseReference = VerseRange ("." VerseRange)*
 * VerseRange = VerseId ("-" VerseId)?
 * VerseId = [0-9]+[a-z]?
 * BookId = [0-9]? Alpha+ '.'?
 * ChapterId = [0-9]+
 *
 * So as an example:
 * - 1Kor 13,1a-3.5b-14.6a|14,23; Jn 14,22-39
 * will become an object tree, which is at the root is an array of BookRef objects.
 */
class ReferenceParser
{

    private $lexer;
    private $input;

    public function __construct($referenceString)
    {
        // the one and only unparseable reference with this implementation
        $referenceString = str_replace('Ének.Én', 'ÉnekÉn', $referenceString);
        $this->lexer = new ReferenceLexer($referenceString);
        $this->input = $referenceString;
    }

    public function bookRefs()
    {
        $bookRefs = [];
        while ($this->lexer->moveNext()) {
            $token = $this->lexer->lookahead;
            if ($token['type'] == ReferenceLexer::T_NUMERIC
                || $token['type'] == ReferenceLexer::T_TEXT
            ) {
                try {
                    $bookRefs[] = $this->bookRef();
                } catch (Throwable $e) {
                    $this->parsingError();
                }                
            }
        }
        return $bookRefs;
    }

    public function bookRef()
    {
        $bookRef = new BookRef($this->bookId());
        $bookRefs[] = $bookRef;
        $nextToken = $this->lexer->glimpse();
        if (null !== $nextToken) { // something is coming, either a new book or something from the book
            if ($nextToken['type'] == ReferenceLexer::T_NUMERIC) { // something from the book
                $this->lexer->moveNext();
                $bookRef->chapterRanges = $this->chapterRanges();
            } else if ($nextToken['type'] == ReferenceLexer::T_BOOK_SEPARATOR) { // an other book or an other chapter range.
                $this->lexer->moveNext(); // skip the separator
            } else if ($nextToken) { // something else, which is an error
                $this->parsingError();
            }
        }
        return $bookRef;
    }

    public function bookId()
    {
        $bookId = '';
        $token = $this->lexer->lookahead;
        if ($token['type'] === ReferenceLexer::T_NUMERIC) { // e.g. the 1 from 1Kor
            $bookId = $bookId . $token['value']; // add the number to the beginning of the bookId
            $this->lexer->moveNext();
            $token = $this->lexer->lookahead;
        }        
        if ($token == null || $token['type'] !== ReferenceLexer::T_TEXT) { // if not text coming, that's an error
            $this->parsingError();
        }
        $bookId = $bookId . $token['value'];
        if (null !== $this->lexer->glimpse() && 
            ($this->lexer->glimpse()['value'] == '.' || $this->lexer->glimpse()['value'] == ',')) { // The book is written as 2Kor. as abbreviation
            $this->lexer->moveNext();
        }
        //print_r("Book ID: $bookId\n");
        return $bookId;
    }

    public function chapterRanges()
    {
        $chapterRanges = [];
        $chapterRanges[] = $this->chapterRange(); // we read something like 6,2-5
        $newRange = false;
        if ($this->lexer->glimpse() != null) {
            if ($this->lexer->glimpse()['type'] == ReferenceLexer::T_BOOK_SEPARATOR) { // we read a chapter range, but can have a new one separated with a ;
                $this->lexer->peek(); // skip the separator
                $newPeek = $this->lexer->peek();
                if ($newPeek !== null && $newPeek['type'] == ReferenceLexer::T_NUMERIC) { // a number is coming, eg 2
                    $textPeek = $this->lexer->peek(); 
                    if ($textPeek !== null && $textPeek['type'] != ReferenceLexer::T_TEXT) {
                        $newRange = true; // a new Book Id is coming, like 1Kor after the book separator
                    } else if ($textPeek == null) {
                        // just a number alone, that will be a new chapter range,
                        $newRange = true;
                    }
                }
                $this->lexer->resetPeek();
            }

            if (!$newRange) { // not a new book
                $newRange = $this->lexer->glimpse()['type'] == ReferenceLexer::T_CHAPTER_RANGE_SEPARATOR // a chapter range separator
                    || $this->lexer->glimpse()['type'] == ReferenceLexer::T_VERSE_RANGE_SEPARATOR; // or a verse range separator coming, so we need to handle that
            }
        }

        if ($newRange) {
            $this->lexer->moveNext(); // skip the separator
            $this->lexer->moveNext(); // move to the next thing
            $chapterRanges = array_merge($chapterRanges, $this->chapterRanges()); // read the new range
        }
        return $chapterRanges;
    }

    public function chapterRange()
    {
        $range = new ChapterRange($this->chapterRef());
        $operator = $this->lexer->glimpse();
        if ($operator !== null) {
            if ($operator['type'] == ReferenceLexer::T_RANGE_OPERATOR) {
                $this->lexer->moveNext();
                $this->lexer->moveNext();
                $range->untilChapterRef = $this->chapterRef();
            } else {
                // a bit hard to understand, but this is used when the ranges are through chapters, 2,3-3,4
                if ($operator['type'] == ReferenceLexer::T_CHAPTER_VERSE_SEPARATOR) {
                    $range->untilChapterRef = $this->chapterRef(true);
                }
            }
        }
        return $range;
    }

    public function chapterRef($fromPreviousChapter = false)
    {
        $chapterRef = new ChapterRef($this->chapterId());
        if ($this->lexer->glimpse() !== null && $this->lexer->glimpse()['type'] == ReferenceLexer::T_TEXT) { // a text is coming, like 3a, accept it
            $this->lexer->moveNext();
            $chapterRef->chapterPart .= $this->lexer->lookahead['value'];
        }
        if ($this->lexer->glimpse() !== null && $this->lexer->glimpse()['type'] == ReferenceLexer::T_CHAPTER_VERSE_SEPARATOR) { // a separator is coming like ,
            $this->lexer->moveNext(); // skip the separator
            $this->lexer->moveNext(); // get to the number or numbers 
            $chapterRef->verseRanges = $this->verseRanges($fromPreviousChapter);
        }
        return $chapterRef;
    }

    public function chapterId()
    {        
        $token = $this->lexer->lookahead;
        $chapterId = $token['value'];
        //print("Chapter id: $chapterId\n");
        return $chapterId;
    }

    public function verseRanges($fromPreviousChapter = false)
    {
        $verseRanges = [];
        $verseRanges[] = $this->verseRange($fromPreviousChapter);
        $nextToken = $this->lexer->peek();
        if ($nextToken !== null) {
            if ($nextToken['type'] == ReferenceLexer::T_VERSE_RANGE_SEPARATOR) 
            {
                $numericPeek = $this->lexer->peek();
                $verseSeparatorPeek = $this->lexer->peek();
                if ($numericPeek !== null && $verseSeparatorPeek !== null
                    && $numericPeek['type'] == ReferenceLexer::T_NUMERIC
                    && $verseSeparatorPeek['type'] == ReferenceLexer::T_CHAPTER_VERSE_SEPARATOR
                ) {
                    $this->lexer->resetPeek();
                } else {
                    $this->lexer->resetPeek();
                    $this->lexer->moveNext();                    
                    if ($this->lexer->glimpse() !== null && $this->lexer->glimpse()['type'] == ReferenceLexer::T_NUMERIC) {
                        $this->lexer->moveNext();
                        $verseRanges = array_merge($verseRanges, $this->verseRanges());
                    }
                }
            }
        }
        $this->lexer->resetPeek();
        return $verseRanges;
    }

    public function verseRange($fromPreviousChapter = false)
    {
        $range = new VerseRange();
        if (!$fromPreviousChapter) {
            $range->verseRef = $this->verseRef(); // if not from a previous chapter, just parse the verse number (like 3 or 3a)
        } else {
            $range->untilVerseRef = $this->verseRef(); 
        }
        $operator = $this->lexer->glimpse(); // let's see if we have actually a range or not.
        if ($operator !== null && $operator['type'] == ReferenceLexer::T_RANGE_OPERATOR) { // yes, that's a range
            $this->lexer->moveNext(); // skip the operator
            $this->lexer->moveNext(); // move to the number itself.
            /**
             * This is some specialty: 12,5-13,14 -> range is through chapters
             */
            $nextOperator = $this->lexer->glimpse(); // let's see, what comes after the number
            if ($nextOperator == null ||
                $nextOperator['type'] != ReferenceLexer::T_CHAPTER_VERSE_SEPARATOR) { // we are either done (end of string) or not chapter-verse separator, so can save the value
                $range->untilVerseRef = $this->verseRef();
            }
        }
        return $range;
    }

    public function verseRef()
    {
        $ref = new VerseRef($this->verseId());
        if ($this->lexer->glimpse() !== null) { 
            if ($this->lexer->glimpse()['type'] == ReferenceLexer::T_TEXT) { // it can be again a text like 2a
                $this->lexer->moveNext(); 
                $ref->versePart = $this->lexer->lookahead['value']; // read it and store it
            }
        }
        return $ref;
    }

    public function verseId()
    {
        $token = $this->lexer->lookahead;
        if ($token['type'] != ReferenceLexer::T_NUMERIC) { // the verse id is not a number, that's an error
            $this->parsingError();
        }
        $verseId = $token['value'];
        //print("Verse id: $verseId\n");
        return $verseId;
    }

    private function parsingError()
    {
        $lookahead = $this->lexer->lookahead;
        $position = 0;
        if ($lookahead !== null) {
            $position = $lookahead['position'];
        }
        throw new ParsingException($position, $this->input);
    }
}