<?php

namespace SzentirasHu\Service\Text\VerseParsers;

use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePart;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePartType;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Xref;

class KNBVerseParser extends DefaultVerseParser
{
    const XREF_REGEXP = '\s*\{([0-9\p{L}][^\}^\{]+)\}';

    protected function replaceTags($rawText) {
        $rawText = preg_replace('/<tv>/', ' ', $rawText);
        $rawText = preg_replace('/<tp>/', ' ', $rawText);
        $rawText = preg_replace('/<tk>/', '', $rawText);
        $rawText = preg_replace('/<br>/', '<br>', $rawText);
        $rawText = preg_replace('/<brx>/', '<br>', $rawText);
        // the current KNB text <i> tags are terrible, they are not opened neither closed properly
        $rawText = preg_replace('/<i>/', '', $rawText);
        $rawText = preg_replace('/<\/i>/', '', $rawText);
        $rawText = preg_replace('/<fs>/', '', $rawText);
        $rawText = preg_replace('/<khiv>/', '', $rawText);
        $rawText = preg_replace('/<\/khiv>/', '', $rawText);
        $purified = preg_replace('/\s*'.self::XREF_REGEXP.'/u', '', $rawText);
        $purified = preg_replace('/<\/?i>/', '', $purified);
        return $purified;
    }

    private function parseXrefs($rawText, VerseData $verseData)
    {
        preg_match_all("/".self::XREF_REGEXP."/u", $rawText, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);
        $count = count($matches[1]);
        for ($i = 0; $i < $count; $i++) {
            $match = $matches[1][$i];
            $xrefPos = $match[1];
            $xrefText = $match[0];
            $xref = new Xref();
            $xref->position = $xrefPos;
            $xref->text = $xrefText;
            $verseData->xrefs[] = $xref;
        }
        return $rawText;
    }

    protected function parseXrefverse($book, $rawVerse, VerseData $verse)
    {
        $xref = new Xref();
        $xref->text = substr($rawVerse->verse, 1, -1);
        $verse->xrefs[]= $xref;
    }



    /**
     * @param $rawVerse
     * @param VerseData $verseData
     */
    protected function parseTextVerse($rawVerse, VerseData $verseData)
    {
        $rawText = $rawVerse->verse;
        $containsBr = false;
        if (substr($rawText, -4) === '<br>') {
            $containsBr = true;
            $rawText = substr($rawText, 0, -4);
        }
        $this->parseXrefs($rawText, $verseData);
        $rawText = $this->replaceTags($rawText);
        $rawText = $this->fixEmTags($rawText);
        $versePart= new VersePart($verseData, $rawText, VersePartType::SIMPLE_TEXT, count($verseData->verseParts));

        $versePart->newline = $containsBr;
        $verseData->verseParts[] = $versePart;

    }

    protected function parsePoemLine($rawVerse, VerseData $verseData)
    {
        $rawVerse->verse = $this->parseXrefs($rawVerse->verse, $verseData);
        parent::parsePoemLine($rawVerse, $verseData);
    }


}