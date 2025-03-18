<?php
/**

 */

namespace SzentirasHu\Service\Text\VerseParsers;

use Log;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Footnote;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePart;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePartType;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Xref;

class DefaultVerseParser extends AbstractVerseParser
{

    protected function parseTextVerse($rawVerse, VerseData $verse)
    {
        $verse->verseParts[] = new VersePart($verse, $rawVerse->verse, VersePartType::SIMPLE_TEXT, count($verse->verseParts));
    }

    protected function parseXrefverse($book, $rawVerse, VerseData $verse)
    {
        $xref = new Xref();
        $xref->text = $rawVerse->verse;
        $verse->xrefs[]= $xref;
    }

    protected function parseHeading($rawVerse, VerseData $verse)
    {
        $level = (int) str_replace('heading','', $rawVerse->getType());
        $heading = preg_replace('/([ ]{0,1})<br([\/]{0,1})>([ ]{0,1})/',' – ',$rawVerse->verse);     
        // https://github.com/molnarm/igemutato
        $regexp = "/((?:[12](?:K(?:[io]r|rón)|Makk?|Pé?t(?:er)?|Sám|T(?:h?essz?|im))|[1-3]Já?n(?:os)?|[1-5]Móz(?:es)?|(?:Ap)?Csel|A(?:gg?|bd)|Ám(?:ós)?|B(?:ár|[ií]r(?:ák)?|ölcs)|Dán|É(?:sa|zs|n(?:ek(?:ek|Én)?)?)|E(?:f(?:éz)?|szt?|z(?:s?dr?)?)|Fil(?:em)?|Gal|H(?:a[bg]|ós)|Iz|J(?:ak|á?n(?:os)?|e[lr]|o(?:el)?|ó(?:[bn]|zs|el)|[Ss]ir(?:alm?)?|úd(?:ás)?|ud(?:it)?)|K(?:iv|ol)|L(?:ev|u?k(?:ács)?)|M(?:al(?:ak)?|á?té?|(?:ár)?k|ik|Törv)|N[áe]h|(?:Ó|O)z|P(?:él|ré)d|R(?:óm|[uú]th?)|S(?:ir(?:alm?)?|ír|z?of|zám)|T(?:er|it|ób)|Z(?:ak|of|s(?:olt|id)?))\.?(?:\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?(?:\s*[-–—]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?)?(?:\s*[\|;]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?(?:\s*[-–—]\s*[0-9]{1,3}(?:[,:]\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?(?:\.\s*[0-9]{1,2}[a-z]?(?:\s*[-–—]\s*[0-9]{1,2}[a-z]?\b(?![,:]))?)*)?)?)*))(?:(?=[^\w])|$)/u";
        $heading = preg_replace_callback($regexp, function($match) {
             return "<a href='{$match[0]}'>$match[0]</a>";
        }, $heading);     
        $verse->verseParts[] = new VersePart($verse, $this->replaceTags($heading), VersePartType::HEADING, count($verse->verseParts), $level);   
    }

    
    protected function parseFootnoteVerse(Verse $rawVerse, VerseData $verse) {
        $footnoteText = $rawVerse->verse;
        $footnoteSaved=false;
        foreach ($verse->footnotes as $footnote) {
            if (!$footnote->text) {
                $footnote->text = $footnoteText;
                $footnoteSaved = true;
            }
        }
        if (!$footnoteSaved) {
            $footnote = new Footnote();
            $footnote->text = $footnoteText;
            $verse->footnotes[] = $footnote;

        }
    }

    protected function parsePoemLine($rawVerse, VerseData $verse)
    {
        $poemLine = $this->replaceTags($rawVerse->verse);
        $poemLine = $this->fixEmTags($poemLine);
        $verse->verseParts[] = new VersePart($verse, $poemLine, VersePartType::POEM_LINE, count($verse->verseParts));
    }


    protected function fixEmTags($rawText) {
        $openTag = '<em>';
        $closeTag = '</em>';
    
        $openTagCount = substr_count($rawText, $openTag);
        $closeTagCount = substr_count($rawText, $closeTag);
    
        // If there are more opening tags than closing tags, add a closing tag at the end
        if ($openTagCount > $closeTagCount) {
            $rawText .= $closeTag;
        }
    
        // If there are more closing tags than opening tags, add an opening tag at the beginning
        if ($closeTagCount > $openTagCount) {
            $rawText = $openTag . $rawText;
        }
    
        // Ensure tags are properly nested
        $fixedText = '';
        $openTags = 0;
        $length = strlen($rawText);
        for ($i = 0; $i < $length; $i++) {
            if (substr($rawText, $i, 4) === $openTag) {
                $openTags++;
                $fixedText .= $openTag;
                $i += 3; // Skip the next 3 characters
            } elseif (substr($rawText, $i, 5) === $closeTag) {
                if ($openTags > 0) {
                    $openTags--;
                    $fixedText .= $closeTag;
                }
                $i += 4; // Skip the next 4 characters
            } else {
                $fixedText .= $rawText[$i];
            }
        }
    
        // If there are any unclosed tags, close them
        while ($openTags > 0) {
            $fixedText .= $closeTag;
            $openTags--;
        }
    
        return $fixedText;
    }
    protected function replaceTags($rawVerse) {
        return $rawVerse;
    }

}