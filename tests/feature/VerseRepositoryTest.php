<?php

namespace SzentirasHu\Test;
use App;
use SzentirasHu\Test\Common\TestCase;


/**

 */

class VerseRepositoryTest extends TestCase {

    public function testVersesInOrder() {
        $repo = App::make(\SzentirasHu\Data\Repository\VerseRepositoryEloquent::class);
        $verses = $repo->getVersesInOrder([3, 2]);        
        $verse = array_pop($verses);
        $this->assertEquals(2, $verse->id);
        $this->assertEquals('TESTTRANS', $verse->translation->abbrev);
        $this->assertEquals('Ter', $verse->book->abbrev);
    }

} 