<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\TestCase;

class GreekTextChapterNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const GREEK_TRANSLATION_ID = 7;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createGreekBook(): void
    {
        // The GNT translation (id 7) is inserted by the add_gnt_translation migration.
        $book = new Book;
        $book->translation_id = self::GREEK_TRANSLATION_ID;
        $book->name = 'Evangélium Máté szerint';
        $book->abbrev = 'Mt';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->order = 1;
        $book->usx_code = 'MAT';
        $book->save();

        foreach ([1, 2, 3] as $chapter) {
            foreach ([1, 2] as $verse) {
                $greekVerse = new GreekVerse;
                $greekVerse->source = 'test';
                $greekVerse->gepi = "MAT.{$chapter}.{$verse}";
                $greekVerse->usx_code = 'MAT';
                $greekVerse->chapter = $chapter;
                $greekVerse->verse = $verse;
                $greekVerse->text = "GReeK chapter {$chapter} verse {$verse}";
                $greekVerse->json = '[]';
                $greekVerse->strongs = '';
                $greekVerse->strong_transliterations = '';
                $greekVerse->strong_normalizations = '';
                $greekVerse->save();
            }
        }
    }

    public function test_bare_book_reference_shows_only_first_chapter(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt');

        $response->assertStatus(200);
        $response->assertSeeText('GReeK chapter 1 verse 1');
        $response->assertDontSeeText('GReeK chapter 2 verse 1');
    }

    public function test_chapter_reference_shows_only_that_chapter(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt2');

        $response->assertStatus(200);
        $response->assertSeeText('GReeK chapter 2 verse 1');
        $response->assertDontSeeText('GReeK chapter 1 verse 1');
        $response->assertDontSeeText('GReeK chapter 3 verse 1');
    }

    public function test_single_verse_reference_highlights_and_scrolls_to_the_requested_verse(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt2,2');

        $response->assertStatus(200);
        $response->assertSee('id="v_MAT_2_2" class="greek-verse mark"', false);
        $response->assertSee('data-scroll-to="MAT_2_2"', false);
        $response->assertDontSee('id="v_MAT_2_1" class="greek-verse mark"', false);
    }

    public function test_verse_range_highlights_every_requested_verse(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt2,1-2');

        $response->assertStatus(200);
        $response->assertSee('id="v_MAT_2_1" class="greek-verse mark"', false);
        $response->assertSee('id="v_MAT_2_2" class="greek-verse mark"', false);
    }

    public function test_chapter_navigation_links_are_rendered(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt2');

        $response->assertStatus(200);
        // Chapter selector links to the other chapters.
        $response->assertSee('href="/GNT/Mt1"', false);
        $response->assertSee('href="/GNT/Mt3"', false);
    }

    public function test_first_chapter_has_no_previous_link(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt1');

        $response->assertStatus(200);
        $response->assertSee('href="/GNT/Mt2"', false);
        $response->assertDontSee('href="/GNT/Mt0"', false);
    }

    public function test_gnt_landing_has_seo_meta_tags(): void
    {
        $response = $this->get('/GNT');

        $response->assertStatus(200);
        $response->assertSee('Görög Újszövetség', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('OpenGNT', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('og:title', false);
        $response->assertSee('og:description', false);
    }

    public function test_gnt_chapter_page_has_specific_title_and_canonical(): void
    {
        $this->createGreekBook();

        $response = $this->get('/GNT/Mt1');

        $response->assertStatus(200);
        $response->assertSee('Evangélium Máté szerint', false);
        $response->assertSee('1. fejezet', false);
        $response->assertSee('canonical', false);
        $response->assertSee('/GNT/Mt1', false);
    }
}
