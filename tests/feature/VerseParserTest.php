<?php

namespace SzentirasHu\Test;

use SzentirasHu\Service\Text\VerseParsers\KGVerseParser;
use SzentirasHu\Service\Text\VerseParsers\KNBVerseParser;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Test\Common\TestCase;


class VerseParserTest extends TestCase
{

    public function testKNBVerseParser()
    {
        $parser = new KNBVerseParser();
        $book = new Book();
        $book->abbrev = "Mt";

        $v = new Verse();
        $chapter = 2;
        $v->chapter = $chapter;
        $numv = 3;
        $v->numv = $numv;
        $v->verse = "abc {2Óz 12,34} xyz {Mt 23,45} zyx";
        $v->tip = \Config::get('translations.definitions.KNB.verseTypes.text.0');
        $v->trans = 3;
        $verseData = $parser->parse([$v], $book);

        $this->assertEquals("abc xyz zyx", $verseData->getText());
        $this->assertCount(2, $verseData->xrefs);
        $this->assertEquals("2Óz 12,34", $verseData->xrefs[0]->text);

    }

    public function testKGVerseParser()
    {

        $parser = new KGVerseParser();
        $book = new Book();
        $book->abbrev = "Mt";

        $v = new Verse();
        $chapter = 2;
        $v->chapter = $chapter;
        $numv = 3;
        $v->numv = $numv;
        $v->verse = "abc " . KGVerseParser::$xrefSigns[0] . " xyz";
        $v->tip = \Config::get('translations.definitions.KG.verseTypes.text.0');
        $v->trans = 4;
        $xrefVerse = new Verse();
        $xrefVerse->chapter = $chapter;
        $xrefVerse->numv = $numv;
        $xrefVerse->verse = KGVerseParser::$xrefSigns[0] . " Mk. 12,34.";
        $xrefVerse->tip = \Config::get('translations.definitions.KG.verseTypes.xref.0');;
        $xrefVerse->trans = 4;
        $verseData = $parser->parse([$v, $xrefVerse], $book);

        $this->assertEquals($v->chapter, $verseData->chapter);
        $this->assertEquals($v->numv, $verseData->numv);
        $this->assertEquals("abc xyz", $verseData->getText());
        $this->assertCount(1, $verseData->xrefs);
        $this->assertEquals("Mk 12,34", $verseData->xrefs[KGVerseParser::$xrefSigns[0]]->text);

        // no inline xref
        $v = new Verse();
        $chapter = 2;
        $v->chapter = $chapter;
        $numv = 3;
        $v->numv = $numv;
        $v->verse = "abc xyz";
        $v->tip = \Config::get('translations.definitions.KG.verseTypes.text.0');
        $v->trans = 4;
        $verseData = $parser->parse([$v, $xrefVerse], $book);

        $this->assertCount(1, $verseData->xrefs);
        $this->assertEquals("Mk 12,34", $verseData->xrefs[KGVerseParser::$xrefSigns[0]]->text);

        $v = new Verse();
        $v->chapter = $chapter;
        $v->numv = $numv;
        $v->verse = "Abc • cde † fgh";
        $v->tip = \Config::get('translations.definitions.KG.verseTypes.text.0');
        $v->trans = 4;
        $ref = new Verse();
        $ref->chapter = $chapter;
        $ref->numv = $numv;
        $ref->verse = "• rész 5,7. † Zsolt. 16,10.";
        $ref->tip = \Config::get('translations.definitions.KG.verseTypes.xref.0');
        $ref->trans = 4;
        $verseData = $parser->parse([$v, $ref], $book);
        $this->assertCount(2, $verseData->xrefs);
        $this->assertEquals("Abc cde fgh", $verseData->getText());
        $this->assertEquals("Mt 5,7", $verseData->xrefs[KGVerseParser::$xrefSigns[0]]->text);
        $this->assertEquals("Zsolt 16,10", $verseData->xrefs[KGVerseParser::$xrefSigns[1]]->text);


    }

} 