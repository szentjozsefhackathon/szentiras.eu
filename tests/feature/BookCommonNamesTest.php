<?php

namespace SzentirasHu\Test;

use PHPUnit\Framework\TestCase;
use SzentirasHu\Data\BookCommonNames;
use SzentirasHu\Data\UsxCodes;

class BookCommonNamesTest extends TestCase
{
    public function test_all_73_books_have_at_least_one_name(): void
    {
        $this->assertCount(73, BookCommonNames::NAMES);
        $this->assertSame([], array_diff(array_keys(BookCommonNames::NAMES), UsxCodes::allUsx()));
        foreach (BookCommonNames::NAMES as $usxCode => $names) {
            $this->assertNotEmpty($names, $usxCode);
            foreach ($names as $name) {
                $this->assertNotSame('', trim($name), "Empty name for {$usxCode}");
            }
        }
    }

    public function test_primary_is_the_first_name(): void
    {
        $this->assertSame('Máté evangéliuma', BookCommonNames::primary('MAT'));
        $this->assertNull(BookCommonNames::primary('NONEXISTENT'));
    }

    public function test_alternatives_leave_out_the_official_name_regardless_of_case_and_article(): void
    {
        $this->assertSame(['Máté evangéliuma'], BookCommonNames::alternativesTo('MAT', 'Az evangélium Máté szerint'));
        $this->assertSame([], BookCommonNames::alternativesTo('MAT', 'Máté evangéliuma'));
        $this->assertSame(['Apokalipszis'], BookCommonNames::alternativesTo('REV', 'A jelenések könyve'));
        $this->assertSame(['Teremtés könyve', 'Genezis'], BookCommonNames::alternativesTo('GEN', 'Mózes első könyve'));
    }

    public function test_title_name_is_the_primary_name_unless_the_own_name_already_is_it(): void
    {
        $this->assertSame('Máté evangéliuma', BookCommonNames::titleNameFor('MAT', 'Az evangélium Máté szerint', false));
        $this->assertNull(BookCommonNames::titleNameFor('PSA', 'A Zsoltárok könyve', false));
        $this->assertNull(BookCommonNames::titleNameFor('GEN', 'Mózes első könyve', true));
    }

    public function test_without_article_drops_the_leading_article_and_capitalises(): void
    {
        $this->assertSame('Jelenések könyve', BookCommonNames::withoutArticle('A jelenések könyve'));
        $this->assertSame('Evangélium Máté szerint', BookCommonNames::withoutArticle('Az evangélium Máté szerint'));
        $this->assertSame('Jób könyve', BookCommonNames::withoutArticle('Jób könyve'));
    }
}
