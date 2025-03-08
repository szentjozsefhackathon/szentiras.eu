<?php

namespace SzentirasHu\Service\Text\VerseParsers;

use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Footnote;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePart;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePartType;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Xref;

class STLVerseParser extends DefaultVerseParser
{
    const FOOTNOTE_REGEXP = '\*+';

    /**
     * @param $rawVerse
     * @param VerseData $verseData
     */
    protected function parseTextVerse($rawVerse, VerseData $verseData)
    {
        $purified = $rawVerse->verse;
        if (preg_match_all("/" . self::FOOTNOTE_REGEXP . "/u", $purified, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $match = $matches[0][$i];
                $footnotePos = $match[1];
                $footnoteSymbol = $match[0];

                if (array_key_exists($footnoteSymbol, $verseData->footnotes)) {
                    $verseData->footnotes[$footnoteSymbol]->position = $footnotePos;
                } else {
                    $footnote = new Footnote();
                    $footnote->position = $footnotePos;
                    $verseData->footnotes[$footnoteSymbol] = $footnote;
                }
            }
            $purified = preg_replace('/\s*' . self::FOOTNOTE_REGEXP . '/u', '', $purified);
        }
        $verseData->verseParts[] = new VersePart($verseData, $purified, VersePartType::SIMPLE_TEXT, count($verseData->verseParts));

    }

    protected function parseFootnoteVerse(Verse $rawVerse, VerseData $verse)
    {
        $footnoteText = $rawVerse->verse;
        preg_match("/(\*+)(.*)/u", $footnoteText, $matches);
        if ($matches) {
            // https://github.com/molnarm/igemutato
            $regexp = "/((?:[12](?:K(?:[io]r|rón)|Makk?|Pé?t(?:er)?|Sám|T(?:h?essz?|im))|[1-3]Já?n(?:os)?|[1-5]Móz(?:es)?|(?:Ap)?Csel|A(?:gg?|bd)|Ám(?:ós)?|B(?:ár|[ií]r(?:ák)?|ölcs)|Dán|É(?:sa|zs|n(?:ek(?:ek|Én)?)?)|E(?:f(?:éz)?|szt?|z(?:s?dr?)?)|Fil(?:em)?|Gal|H(?:a[bg]|ós)|Iz|J(?:ak|á?n(?:os)?|e[lr]|o(?:el)?|ó(?:[bn]|zs|el)|[Ss]ir(?:alm?)?|úd(?:ás)?|ud(?:it)?)|K(?:iv|ol)|L(?:ev|u?k(?:ács)?)|M(?:al(?:ak)?|á?té?|(?:ár)?k|ik|Törv)|N[áe]h|(?:Ó|O)z|P(?:él|ré)d|R(?:óm|[uú]th?)|S(?:ir(?:alm?)?|ír|z?of|zám)|T(?:er|it|ób)|Z(?:ak|of|s(?:olt|id)?))\.?(?:\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?(?:\s*[-–—]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?)?(?:\s*[\|;]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?(?:\s*[-–—]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?)?)*))(?:(?=[^\w])|$)/u";
            $footnoteText = preg_replace_callback($regexp, function($match) {
                return "<a href='{$match[0]}'>$match[0]</a>";
            }, $matches[2]);
            $footnoteSymbol = $matches[1];

            if (array_key_exists($footnoteSymbol, $verse->footnotes)) {
                $verse->footnotes[$footnoteSymbol]->text = $footnoteText;
            } else {
                $footnote = new Footnote();
                $footnote->text = $footnoteText;
                $verse->footnotes[$footnoteSymbol] = $footnote;
            }
        }

    }

}