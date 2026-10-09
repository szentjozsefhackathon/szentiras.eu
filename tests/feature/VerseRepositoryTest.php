<?php

namespace SzentirasHu\Test;

use App;
use SzentirasHu\Test\Common\FastDatabaseTestCase;


/**
 
 */

class VerseRepositoryTest extends FastDatabaseTestCase
{
    public function testVersesInOrder() {
        $repo = App::make(\SzentirasHu\Data\Repository\VerseRepositoryEloquent::class);
        
        // Get actual verse IDs from the database
        $allVerses = \SzentirasHu\Data\Entity\Verse::orderBy('id')->limit(2)->get();
        $this->assertCount(2, $allVerses);
        
        $verseIds = $allVerses->pluck('id')->toArray();
        $verses = $repo->getVersesInOrder($verseIds);
        
        // Verify we got verses back in the correct order
        $this->assertCount(2, $verses);
        $verse = array_pop($verses);
        $this->assertIsObject($verse);
        $this->assertEquals('TESTTRANS', $verse->translation->abbrev);
        $this->assertEquals('Ter', $verse->book->abbrev);
    }

    public function testChapterVersesKeepTheStoredOrderWithinAVerse() {
        $repo = App::make(\SzentirasHu\Data\Repository\VerseRepositoryEloquent::class);

        $poemLine = $this->createVerse(10, 8, 902, 'idézett verssor');
        $text = $this->createVerse(10, 8, 901, 'prózai folytatás');

        $verses = $repo->getTranslatedChapterVerses(99101, [10]);

        $this->assertSame([$poemLine->id, $text->id], $verses->pluck('id')->all());
    }

    private function createVerse(int $chapter, int $numv, int $tip, string $content): \SzentirasHu\Data\Entity\Verse {
        $verse = new \SzentirasHu\Data\Entity\Verse();
        $verse->trans = 1001;
        $verse->gepi = 101000000 + $chapter * 1000 + $numv;
        $verse->usx_code = 'GEN';
        $verse->book_id = 99101;
        $verse->chapter = $chapter;
        $verse->numv = $numv;
        $verse->tip = $tip;
        $verse->verse = $content;
        $verse->verseroot = $content;
        $verse->ido = '2025-02-05';
        $verse->save();

        return $verse;
    }

}