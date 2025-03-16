<?php

namespace SzentirasHu\Test;

use App;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ChapterRange;
use SzentirasHu\Service\Reference\ChapterRef;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Test\Common\TestCase;

class CanonicalReferenceTest extends TestCase
{

    public function testCanonicalBookString()
    {
        $s = "1Móz";
        $ref = CanonicalReference::fromString($s);
        $this->assertCount(1, $ref->bookRefs);
        $this->assertEquals($s, $ref->bookRefs[0]->bookId);

        $s = "  1Móz.;   ";
        $ref = CanonicalReference::fromString($s);
        $this->assertCount(1, $ref->bookRefs);
        $this->assertEquals("1Móz", $ref->bookRefs[0]->bookId);

        $s = "2Móz; 2Kor";
        $ref = CanonicalReference::fromString($s);
        $this->assertCount(2, $ref->bookRefs);
        $this->assertEquals('2Móz', $ref->bookRefs[0]->bookId);
        $this->assertEquals('2Kor', $ref->bookRefs[1]->bookId);

        $s = "2Móz; Kor;";
        $ref = CanonicalReference::fromString($s);
        $this->assertCount(2, $ref->bookRefs);
        $this->assertEquals('2Móz', $ref->bookRefs[0]->bookId);
        $this->assertEquals('Kor', $ref->bookRefs[1]->bookId);

    }

    public function testCanonicalBookWithChapterString()
    {
        $s = "Kor 13";
        $ref = CanonicalReference::fromString($s);
        $bookRef = $ref->bookRefs[0];
        $this->assertEquals("Kor", $bookRef->bookId);
        $this->assertCount(1, $bookRef->chapterRanges);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);

        $bookRef = CanonicalReference::fromString("1Móz 2b;")->bookRefs[0];
        $this->assertEquals("1Móz", $bookRef->bookId);
        $this->assertEquals("2", $bookRef->chapterRanges[0]->chapterRef->chapterId);

        $bookRef = CanonicalReference::fromString("1Móz2b;")->bookRefs[0];
        $this->assertEquals("1Móz", $bookRef->bookId);
        $this->assertEquals("2", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("b", $bookRef->chapterRanges[0]->chapterRef->chapterPart);

        $bookRef = CanonicalReference::fromString("1Móz 3-4")->bookRefs[0];
        $this->assertEquals("1Móz", $bookRef->bookId);
        $this->assertEquals("3", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("4", $bookRef->chapterRanges[0]->untilChapterRef->chapterId);

        $bookRef = CanonicalReference::fromString("1Móz 3-4|6-7")->bookRefs[0];
        $this->assertEquals("1Móz", $bookRef->bookId);
        $this->assertEquals("3", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("4", $bookRef->chapterRanges[0]->untilChapterRef->chapterId);
        $this->assertEquals("6", $bookRef->chapterRanges[1]->chapterRef->chapterId);
        $this->assertEquals("7", $bookRef->chapterRanges[1]->untilChapterRef->chapterId);

        $ref = CanonicalReference::fromString("1Móz 3-4|6-7;2Kor 13");
        $bookRef = $ref->bookRefs[0];
        $this->assertEquals("1Móz", $bookRef->bookId);
        $this->assertEquals("3", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("4", $bookRef->chapterRanges[0]->untilChapterRef->chapterId);
        $this->assertEquals("6", $bookRef->chapterRanges[1]->chapterRef->chapterId);
        $this->assertEquals("7", $bookRef->chapterRanges[1]->untilChapterRef->chapterId);
        $bookRef = $ref->bookRefs[1];
        $this->assertEquals("2Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
    }

    public function testCanonicalBookWithChapterVerses()
    {
        $bookRef = CanonicalReference::fromString("Kor 13,1")->bookRefs[0];
        $this->assertEquals("Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("1", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);

        $bookRef = CanonicalReference::fromString("Kor 13:1")->bookRefs[0];
        $this->assertEquals("Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("1", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);

        $ref = CanonicalReference::fromString("1 Kor 13,2-22. ");
        $bookRef = $ref->bookRefs[0];
        $this->assertCount(1, $ref->bookRefs);
        $this->assertEquals("1Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("2", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);

        $bookRef = CanonicalReference::fromString("Kor 13,1a-4b")->bookRefs[0];
        $this->assertEquals("Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("1", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);
        $this->assertEquals("a", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->versePart);
        $this->assertEquals("4", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->untilVerseRef->verseId);

        $bookRef = CanonicalReference::fromString("Kor 13,1a-14,2b")->bookRefs[0];
        $this->assertEquals("Kor", $bookRef->bookId);
        $this->assertEquals("13", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertEquals("1", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);
        $this->assertEquals("a", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->versePart);
        $this->assertEquals("14", $bookRef->chapterRanges[0]->untilChapterRef->chapterId);
        $this->assertEquals("2", $bookRef->chapterRanges[0]->untilChapterRef->verseRanges[0]->untilVerseRef->verseId);
        $this->assertEquals("b", $bookRef->chapterRanges[0]->untilChapterRef->verseRanges[0]->untilVerseRef->versePart);

        $canonicalRef = CanonicalReference::fromString("Kor 13,1a-14,2b;14,2");
        $this->assertEquals("Kor 13,1a-14,2b;14,2", $canonicalRef->toString());
        $canonicalRef = CanonicalReference::fromString("Kor 13,1|14");
        $this->assertEquals("Kor 13,1;14", $canonicalRef->toString());
        $canonicalRef = CanonicalReference::fromString("Kor 13,1-14,5");
        $this->assertEquals("Kor 13,1-14,5", $canonicalRef->toString());

        $canonicalRef = CanonicalReference::fromString("Kor 13,1-13,2");
        $this->assertEquals("Kor 13,1-13,2", $canonicalRef->toString());
        
        $canonicalRef = CanonicalReference::fromString("Kor 13,1-13,1");
        $this->assertEquals("Kor 13,1-13,1", $canonicalRef->toString());
    }

    public function testCanonicalBookChapterVerseComplicated()
    {
        $ref = CanonicalReference::fromString("2Sám 7,4-5a.12-14a.16; Zs 88,2-29; 2Kor 4,13a.14b-5,1b.6-8.7|2,3.4-5,6.7-12");
        $this->assertCount(3, $ref->bookRefs);
        $bookRef = $ref->bookRefs[0];
        $this->assertEquals("2Sám", $bookRef->bookId);
        $this->assertCount(1, $bookRef->chapterRanges);
        $this->assertEquals("7", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $this->assertCount(3, $bookRef->chapterRanges[0]->chapterRef->verseRanges);
        $this->assertEquals("4", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->verseRef->verseId);
        $this->assertEquals("5", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->untilVerseRef->verseId);
        $this->assertEquals("a", $bookRef->chapterRanges[0]->chapterRef->verseRanges[0]->untilVerseRef->versePart);
        $this->assertEquals("12", $bookRef->chapterRanges[0]->chapterRef->verseRanges[1]->verseRef->verseId);
        $this->assertEquals("14", $bookRef->chapterRanges[0]->chapterRef->verseRanges[1]->untilVerseRef->verseId);
        $this->assertEquals("16", $bookRef->chapterRanges[0]->chapterRef->verseRanges[2]->verseRef->verseId);
        $this->assertNull($bookRef->chapterRanges[0]->chapterRef->verseRanges[2]->untilVerseRef);
        $bookRef = $ref->bookRefs[1];
        $this->assertEquals("Zs", $bookRef->bookId);
        $this->assertEquals("88", $bookRef->chapterRanges[0]->chapterRef->chapterId);
        $bookRef = $ref->bookRefs[2];
        $this->assertEquals("2Kor", $bookRef->bookId);
        $this->assertCount(2, $bookRef->chapterRanges[0]->chapterRef->verseRanges);
        $this->assertCount(3, $bookRef->chapterRanges[0]->untilChapterRef->verseRanges);
        $this->assertEquals("5", $bookRef->chapterRanges[0]->untilChapterRef->chapterId);
        $this->assertEquals("8", $bookRef->chapterRanges[0]->untilChapterRef->verseRanges[1]->untilVerseRef->verseId);
        $this->assertCount(2, $bookRef->chapterRanges[1]->untilChapterRef->verseRanges);
        $this->assertEquals("12", $bookRef->chapterRanges[1]->untilChapterRef->verseRanges[1]->untilVerseRef->verseId);

    }

    public function testToCanonicalString()
    {
        $this->assertEquals("2Sám", CanonicalReference::fromString("2Sám")->toString());
        $this->assertEquals("2Sám; 2Kor", $s = CanonicalReference::fromString("  2 Sám. ; 2Kor ;")->toString());
        $this->assertEquals("2Sám 3; 1Kor 2-5; 2Kor 5;6", CanonicalReference::fromString("2Sám 3; 1Kor2-5;2Kor5|6")->toString());
        $this->assertEquals("2Sám 3; 1Kor 2-5; 2Kor 5;6", CanonicalReference::fromString("2Sám 3; 1Kor2-5;2Kor5;6")->toString());
        $this->assertEquals("2Kor 3,1", CanonicalReference::fromString("2Kor 3, 1")->toString());
        $this->assertEquals("2Kor 3,1", CanonicalReference::fromString("2Kor 3, 1.")->toString());
        $this->assertEquals("Zs 88,2-29", CanonicalReference::fromString("Zs 88:2-29")->toString());
        $this->assertEquals("Bölcs 2,1a.12-22", CanonicalReference::fromString("Bölcs 2,1a.12-22")->toString());

        $this->assertEquals("1Kor 3,12;13,36", CanonicalReference::fromString("1Kor 3,12.13,36")->toString());
        $complicatedString = ("Kor4:13-5:6;2,3.4-5,6.7-12");
        $this->assertEquals("Kor 4,13-5,6;2,3.4-5,6.7-12", CanonicalReference::fromString($complicatedString)->toString());

        $complicatedString = ("2Sám7:4-5a.12-14a.16;Zs88,2-29;2Kor4:13a.14b-5:1b.6-8.7;2,3.4-5,6.7-12");
        $this->assertEquals("2Sám 7,4-5a.12-14a.16; Zs 88,2-29; 2Kor 4,13a.14b-5,1b.6-8.7;2,3.4-5,6.7-12", CanonicalReference::fromString($complicatedString)->toString());
        $this->assertEquals("1Kor 2,3-4", CanonicalReference::fromString("1Kor 2,3-4.")->toString());
        $this->assertEquals("ÉnekÉn", CanonicalReference::fromString("Ének.Én")->toString());

    }

    public function testErrorHandling()
    {
        $this->assertEquals('Mk 1,1', CanonicalReference::fromString('Mk. 1,1')->toString());
        $this->assertEquals('Mk 1,1', CanonicalReference::fromString('Mk, 1,1')->toString());

        $this->expectException(\SzentirasHu\Service\Reference\ParsingException::class);
        CanonicalReference::fromString("1,2");

    }

    public function testTranslatedBookId()
    {
        $referenceService = App::make(\SzentirasHu\Service\Reference\ReferenceService::class);
        $ref = CanonicalReference::fromString("2Moz");
        $translatedRef = $referenceService->translateReference($ref, 1001);
        $this->assertEquals("Kiv", $translatedRef->bookRefs[0]->bookId);

        $ref = CanonicalReference::fromString("Kivonulas 2:3.4-5; 1Moz 4,5-6,12");
        $translatedRef = $referenceService->translateReference($ref, 1001);
        $this->assertEquals("Kiv", $translatedRef->bookRefs[0]->bookId);
        $this->assertEquals("Ter", $translatedRef->bookRefs[1]->bookId);

        $this->assertEquals("Kiv 2,3.4-5; Ter 4,5-6,12", $translatedRef->toString());
    }

    public function testIsValidBookRef()
    {
        $this->assertTrue(CanonicalReference::isValid("Jn 3,2-5"));
        $this->assertFalse(CanonicalReference::isValid("ne félj"));
        $this->assertFalse(CanonicalReference::isValid("Jn 3,2-5,,"));
        $this->assertTrue(CanonicalReference::isValid("9Sira 10,2-5"));
    }

    public
    function testIsExistingBookRef()
    {
        /** @var ReferenceService $referenceService */
        $referenceService = App::make(ReferenceService::class);
        $bookRefs = $referenceService->getExistingBookRefs(CanonicalReference::fromString('1Móz 2,3.4-5'));
        $translatedBookRef = $referenceService->translateBookRef($bookRefs[0], 1001);
        $this->assertEquals("Ter", $translatedBookRef->bookId);
    }

    public function testIsBookLevel()
    {
        $this->assertTrue(CanonicalReference::fromString("Mt")->isBookLevel());
        $this->assertFalse(CanonicalReference::fromString("Mt1")->isBookLevel());

    }

    public
    function testChapterRange()
    {
        $range = CanonicalReference::fromString("Mt 2,1-5")->bookRefs[0]->chapterRanges[0];
        $this->assertFalse($range->hasVerse(1, 1));
        $this->assertFalse($range->hasVerse(5, 1));
        $this->assertFalse($range->hasVerse(2, 6));
        $this->assertTrue($range->hasVerse(2, 3));

        $range = CanonicalReference::fromString("Mt 2-4")->bookRefs[0]->chapterRanges[0];
        $this->assertFalse($range->hasVerse(1, 1));
        $this->assertFalse($range->hasVerse(5, 1));
        $this->assertTrue($range->hasVerse(3, 99));
        $this->assertTrue($range->hasVerse(2, 1));
        $this->assertTrue($range->hasVerse(4, 99));

        $range = CanonicalReference::fromString("Mt 1,2-3,4;3,6-8")->bookRefs[0]->chapterRanges[0];
        $this->assertFalse($range->hasVerse(1, 1));
        $this->assertTrue($range->hasVerse(1, 2));
        $this->assertTrue($range->hasVerse(2, 99));
        $this->assertTrue($range->hasVerse(3, 3));
        $this->assertTrue($range->hasVerse(3, 4));

        $range = CanonicalReference::fromString("Mt 1,2-3,4")->bookRefs[0]->chapterRanges[0];
        $this->assertFalse($range->hasVerse(1, 1));
        $this->assertFalse($range->hasVerse(3, 5));
        $this->assertTrue($range->hasVerse(1, 4));
        $this->assertTrue($range->hasVerse(2, 1));
        $this->assertTrue($range->hasVerse(3, 2));

        $range = CanonicalReference::fromString("Mt 1,1-1,1")->bookRefs[0]->chapterRanges[0];
        // $range->hasVerse(1, 1);
        $this->assertTrue($range->hasVerse(1, 1));
        $this->assertFalse($range->hasVerse(1, 2));

        $range = CanonicalReference::fromString("Mt 1,2-1,3")->bookRefs[0]->chapterRanges[0];
        $this->assertFalse($range->hasVerse(1, 1));
        $this->assertTrue($range->hasVerse(1, 2));
        $this->assertTrue($range->hasVerse(1, 3));
        $this->assertFalse($range->hasVerse(1, 4));
        $this->assertFalse($range->hasVerse(2, 1));

    }

    public function testDashes()
    {
        $this->assertEquals("Mt 2,1-2", CanonicalReference::fromString("Mt 2,1–2")->toString());
        $this->assertEquals("Mt 2,1-2", CanonicalReference::fromString("Mt 2,1—2")->toString());
    }

    public function testCanonicalUrl()
    {
        $referenceService = App::make(ReferenceService::class);
        $ref = "Kiv 1,9-10; 1Móz 22";
        $this->assertEquals("TESTTRANS/Kiv1,9-10;Ter22",
            $referenceService->getCanonicalUrl(CanonicalReference::fromString($ref), 1001));
    }

    public function testIsOneChapter()
    {
        $this->assertTrue(CanonicalReference::fromString('ApCsel 1')->isOneChapter());
        $this->assertFalse(CanonicalReference::fromString('ApCsel 1,2')->isOneChapter());
        $this->assertFalse(CanonicalReference::fromString('ApCsel 1,2-5')->isOneChapter());
        $this->assertFalse(CanonicalReference::fromString('ApCsel 3-4')->isOneChapter());
        $this->assertFalse(CanonicalReference::fromString('ApCsel')->isOneChapter());
    }

    public function testPrevNextChapter()
    {
        $referenceService = App::make(ReferenceService::class);
        $ref = CanonicalReference::fromString('Ter 50');
        list($prevRef, $nextRef) = $referenceService->getPrevNextChapter($ref, 1001);
        $this->assertEquals('Ter 49', $prevRef->toString());
        $this->assertEquals(false, $nextRef);

        $ref = CanonicalReference::fromString('Kiv 1');
        list($prevRef, $nextRef) = $referenceService->getPrevNextChapter($ref, 1001);
        $this->assertEquals('Kiv 2', $nextRef->toString());
        $this->assertEquals(false, $prevRef);

        $ref = CanonicalReference::fromString('Kiv 2');
        list($prevRef, $nextRef) = $referenceService->getPrevNextChapter($ref, 1001);
        $this->assertEquals('Kiv 3', $nextRef->toString());
        $this->assertEquals('Kiv 1', $prevRef->toString());
    }

    public function testCollectChapterIds()
    {
        $range = new ChapterRange(new ChapterRef(2), new ChapterRef(4));
        $ids = CanonicalReference::collectChapterIds($range);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
        $this->assertContains(4, $ids);
        $this->assertNotContains(1, $ids);
        $this->assertNotContains(5, $ids);

        $range = new ChapterRange(new ChapterRef(2), new ChapterRef(2));
        $ids = CanonicalReference::collectChapterIds($range);
        $this->assertContains(2, $ids);
        $this->assertNotContains(3, $ids);


    }
    
    public function testGetReferenceFromBookChapterVerse()
    {
        $ref = CanonicalReference::fromBookChapterVerse("2Kor", "3", "4");
        $this->assertEquals("2Kor 3,4", $ref->toString());
    }

    public function testGetUsxVerseId() {
        $ref = CanonicalReference::fromString("2Kor 3,4");
        $this->assertEquals("2CO 3:4", $ref->toUsxVerseId());
    }


}
 