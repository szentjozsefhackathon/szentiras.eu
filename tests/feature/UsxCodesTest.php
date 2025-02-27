<?php

namespace SzentirasHu\Test;

use App;
use Mockery;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Data\UsxCodes;

class UsxCodesTest extends TestCase
{

    public function testNewTestament(): void
    {
        $newTestamentBooks = UsxCodes::newTestamentUsx();
        $this->assertCount(
            27,
            $newTestamentBooks,
            "The New Testament books count should be 27."
        );
    }

    public function testOldTestament(): void
    {
        $oldTestamentBooks = UsxCodes::oldTestamentUsx();
        $this->assertCount(
            46,
            $oldTestamentBooks,
            "The Old Testament books count should be 46."
        );
    }

    public function testAllUsx(): void
    {
        $allUsx = UsxCodes::allUsx();
        $this->assertCount(
            73,
            $allUsx,
            "All Holy Scripture books count should be 73."
        );
    }

    public function testGetUsxFromBookAbbrevAndTranslation(): void
    {
        $this->checkUsxCode('default', 'Ter', 'GEN');
        $this->checkUsxCode('SZIT', 'Ter', 'GEN');
        $this->checkUsxCode('default', '1Móz', 'GEN');
        $this->checkUsxCode('default', 'Sirák', 'SIR');
        $this->checkUsxCode('RUF', 'Sir', 'LAM');
        $this->checkUsxCode('RUF', 'Sirák', null);
        $this->checkUsxCode('SZIT', 'Sir', 'SIR');
        $this->checkUsxCode('default', 'Jud', 'JUD');
        $this->checkUsxCode('default', 'Júd', 'JUD');
        $this->checkUsxCode('default', 'Judit', 'JDT');
        $this->checkUsxCode('SZIT', 'Jud', 'JDT');
        $this->checkUsxCode('SZIT', 'Júd', 'JUD');
    }

    public function testGetUsxFromBookAbbrevAndTranslationEdgeCases(): void
    {
        $this->checkUsxCode('noSuchTranslation', 'Ter', 'GEN');
        $this->checkUsxCode('default', 'noSuchAbbrev', null);
        $this->checkUsxCode('noSuchTranslation', 'noSuchAbbrev', null);
    }

    private function checkUsxCode(string $translation, string $bookAbbrev, ?string $expectedUsxCode): void
    {
        $this->assertEquals(
            $expectedUsxCode,
            UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translation),
            "The book abbreviation $bookAbbrev should be mapped to $expectedUsxCode for $translation."
        );
    }
}
