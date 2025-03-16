<?php
/**

 */
namespace SzentirasHu\Service;

use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Service\Text\VerseParsers\VerseParser;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Service\Reference\BookRef;
use SzentirasHu\Service\Text\VerseParsers\VerseParserService;

/**
 * this class contains verses and their metadata for displaying etc.
 * @package SzentirasHu\Service
 */
class VerseContainer
{
    public Book $book;
    
    public ?BookRef  $bookRef;
    /**
     * @var \SzentirasHu\Service\Text\VerseParsers\VerseParser
     */
    private $verseParser;
    /**
     * @var Verse[][]
     */
    public $rawVerses;

    function __construct(Book $book, $bookRef=null)
    {
        $this->book = $book;
        $this->rawVerses = [];
        $this->verseParser = \App::make('\SzentirasHu\Service\Text\VerseParsers\VerseParserService')->getParser($book->translation_id);
        $this->bookRef = $bookRef;
    }

    public function addVerse(Verse $verse)
    {
        $verseKey = $verse->gepi;
        if (!array_key_exists($verseKey, $this->rawVerses)) {
            $this->rawVerses[$verseKey] = [];
        }
        $this->rawVerses[$verseKey][] = $verse;
    }

    /**
     * @return VerseData[]
     */
    public function getParsedVerses()
    {
        $verseData = [];
        foreach ($this->rawVerses as $gepi => $rawVerses) {
            $parsedVerseData = $this->verseParser->parse($rawVerses, $this->book);
            $parsedVerseData->gepi = $gepi;
            $parsedVerseData->book = $this->book;
            $verseData[] = $parsedVerseData;
        }
        return $verseData;

    }

}