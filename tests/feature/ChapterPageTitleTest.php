<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Config;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The seeded TESTTRANS book "Ter." (GEN) has verses in chapter 2.
 */
class ChapterPageTitleTest extends FastDatabaseTestCase
{
    public function test_chapter_title_leads_with_the_popular_book_name_and_the_searched_translation_name(): void
    {
        Config::set('translations.definitions.TESTTRANS.commonName', 'Teszt Biblia');

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<title>Teremtés könyve 2. fejezet – Teszt Biblia (TESTTRANS)</title>', false);
    }

    public function test_chapter_title_falls_back_to_the_translation_name(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<title>Teremtés könyve 2. fejezet – Translation Name 1 (TESTTRANS)</title>', false);
    }

    public function test_protestant_translation_keeps_its_own_book_name_without_article(): void
    {
        Translation::query()->whereKey(1001)->update(['denom' => 'protestáns']);
        Book::query()->whereKey(99101)->update(['name' => 'A Mózes első könyve']);

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<title>Mózes első könyve 2. fejezet – Translation Name 1 (TESTTRANS)</title>', false);
    }

    public function test_heading_keeps_the_official_book_name(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<h1 class="h4">Ter. – 2. fejezet</h1>', false);
    }

    public function test_description_starts_with_the_searched_chapter_name_and_the_reference(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<meta name="description" content="Teremtés könyve 2. fejezet (Ter 2) – Translation Name 1.', false);
    }

    public function test_psalm_title_is_the_psalm_number(): void
    {
        Book::query()->whereKey(99101)->update(['usx_code' => 'PSA', 'abbrev' => 'Zsolt', 'name' => 'A Zsoltárok könyve']);
        Verse::query()->where('book_id', 99101)->update(['usx_code' => 'PSA']);

        $this->get('/TESTTRANS/Zsolt2')
            ->assertOk()
            ->assertSee('<title>2. zsoltár – Translation Name 1 (TESTTRANS)</title>', false);
    }

    public function test_verse_range_title_is_unchanged(): void
    {
        $this->get('/TESTTRANS/Ter2,3-4')
            ->assertOk()
            ->assertSee('<title>Ter 2,3-4 – Translation Name 1</title>', false);
    }
}
