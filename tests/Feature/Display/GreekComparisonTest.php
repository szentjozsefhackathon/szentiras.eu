<?php

namespace Tests\Feature\Display;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class GreekComparisonTest extends FastDatabaseTestCase
{
    private const TEST_TRANSLATION_ID = 1001;

    private function setUpNewTestamentData(): void
    {
        $book = new Book;
        $book->order = 200;
        $book->abbrev = 'Mt';
        $book->name = 'Máté';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->usx_code = 'MAT';
        $book->translation_id = self::TEST_TRANSLATION_ID;
        $book->save();

        $this->createHungarianVerse($book->id, 1, 1, 'Magyar Máté evangéliuma');

        $this->createGreekVerse(1, 1, 'Βιβλος γενεσεως', 'G976 G1078', 'biblos geneseos');
    }

    private function createHungarianVerse(int $bookId, int $chapter, int $numv, string $text): void
    {
        $verse = new Verse;
        $verse->trans = self::TEST_TRANSLATION_ID;
        $verse->gepi = '993000'.str_pad((string) $chapter, 3, '0', STR_PAD_LEFT).str_pad((string) $numv, 3, '0', STR_PAD_LEFT).'000';
        $verse->usx_code = 'MAT';
        $verse->book_id = $bookId;
        $verse->chapter = $chapter;
        $verse->numv = $numv;
        $verse->tip = 6;
        $verse->verse = $text;
        $verse->verseroot = 'verseroot';
        $verse->ido = '2025-02-05';
        $verse->save();
    }

    private function createGreekVerse(int $chapter, int $verse, string $text, string $strongs = '', string $translit = ''): void
    {
        $greekVerse = new GreekVerse;
        $greekVerse->source = 'test';
        $greekVerse->gepi = "MAT.{$chapter}.{$verse}";
        $greekVerse->usx_code = 'MAT';
        $greekVerse->chapter = $chapter;
        $greekVerse->verse = $verse;
        $greekVerse->text = $text;
        $greekVerse->json = '[]';
        $greekVerse->strongs = $strongs;
        $greekVerse->strong_transliterations = $translit;
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();
    }

    /**
     * The GNT reading page (/GNT/...) reads books from translation id 7, so a Greek book row
     * must exist there independently of the Hungarian test translation.
     */
    private function createGreekBook(): void
    {
        $gnt = Translation::where('abbrev', 'GNT')->firstOrFail();
        $book = new Book;
        $book->order = 1;
        $book->abbrev = 'Mt';
        $book->name = 'Evangélium Máté szerint';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->usx_code = 'MAT';
        $book->translation_id = $gnt->id;
        $book->save();
    }

    private function enableGreekComparison(): void
    {
        $gnt = Translation::where('abbrev', 'GNT')->firstOrFail();
        \Config::set('settings.enabledTranslations', [self::TEST_TRANSLATION_ID, $gnt->id, 9]);
        \Config::set('translations.ids', \Config::get('translations.ids', []) + [
            self::TEST_TRANSLATION_ID => 'TESTTRANS',
            $gnt->id => 'GNT',
        ]);
        \Config::set('translations.definitions.TESTTRANS', [
            'verseTypes' => [
                'text' => [6, 901],
                'poemLine' => [902],
            ],
            'textSource' => 'local',
            'id' => self::TEST_TRANSLATION_ID,
            'order' => 50,
            'toc_heading_levels' => '5-9',
        ]);
        Cache::flush();
    }

    public function test_greek_text_is_shown_clickable_next_to_the_hungarian_translation(): void
    {
        $this->setUpNewTestamentData();
        $this->enableGreekComparison();

        $response = $this->get('/TESTTRANS/Mt1,1?compare=GNT');

        $response->assertStatus(200);

        // Both the Hungarian text and the Greek text are present.
        $response->assertSeeText('Magyar Máté evangéliuma');
        $response->assertSee('Βιβλος', false);
        $response->assertSee('γενεσεως', false);

        // Each Greek word is a clickable span wired to the word explanation panel.
        $response->assertSee('class="greekWord clickable-greek"', false);
        $response->assertSee('data-usx="MAT"', false);
        $response->assertSee('id="greekWordOffcanvas"', false);

        // The two translations are laid out in the verse-by-verse alignment grid.
        $response->assertSee('class="compareGrid', false);

        // The Greek verse number is rendered and stays out of the AI-tools driven `.parsedVerses`
        // wrapper so it remains visible regardless of the AI-tools state.
        $response->assertSee('<span class="numv"><sup>1</sup></span>', false);
    }

    public function test_verses_are_aligned_row_by_row_in_the_grid(): void
    {
        $this->setUpNewTestamentData();
        $book = Book::where('translation_id', self::TEST_TRANSLATION_ID)->where('usx_code', 'MAT')->firstOrFail();
        $this->createHungarianVerse($book->id, 1, 2, 'Második magyar vers');
        $this->createGreekVerse(1, 2, 'δευτερος στιχος');
        $this->enableGreekComparison();

        $response = $this->get('/TESTTRANS/Mt1,1-2?compare=GNT');

        $response->assertStatus(200);
        // Both verses of both translations are present and aligned in the grid.
        $response->assertSee('class="compareGrid', false);
        $response->assertSeeText('Magyar Máté evangéliuma');
        $response->assertSeeText('Második magyar vers');
        $response->assertSee('γενεσεως', false);
        $response->assertSee('δευτερος', false);
    }

    public function test_gnt_reading_page_shows_a_parallel_translation(): void
    {
        $this->setUpNewTestamentData();
        $this->createGreekBook();
        $this->enableGreekComparison();

        $response = $this->get('/GNT/Mt1?compare=TESTTRANS');

        $response->assertStatus(200);
        // The aligned grid is used, Greek on the left and the Hungarian translation on the right.
        $response->assertSee('class="compareGrid', false);
        $response->assertSee('γενεσεως', false);
        $response->assertSee('class="greekWord clickable-greek"', false);
        $response->assertSeeText('Magyar Máté evangéliuma');
    }

    public function test_gnt_parallel_reading_preserves_and_highlights_the_requested_verse(): void
    {
        $this->setUpNewTestamentData();
        $this->createGreekBook();
        $this->enableGreekComparison();

        $response = $this->get('/GNT/Mt1,1?compare=TESTTRANS');

        $response->assertStatus(200);
        $response->assertSee('id="v_MAT_1_1" class="compareGreek greek greekSection mark" lang="grc"', false);
        $response->assertSee('/GNT/Mt%201,1?compare=TESTTRANS', false);
    }

    public function test_gnt_reading_page_offers_comparison_dropdown(): void
    {
        $this->setUpNewTestamentData();
        $this->createGreekBook();
        $this->enableGreekComparison();

        $response = $this->get('/GNT/Mt1');

        $response->assertStatus(200);
        // The prominent parallel-reading control sits above the text, labelled and
        // linking back to the GNT chapter with the chosen translation.
        $response->assertSeeText('Párhuzamos olvasás fordítással:');
        $response->assertSee('/GNT/Mt1?compare=TESTTRANS', false);
    }

    public function test_gnt_landing_page_promotes_grammar_analysis_and_parallel_reading(): void
    {
        $this->createGreekBook();
        $this->enableGreekComparison();

        $response = $this->get('/GNT');

        $response->assertStatus(200);
        $response->assertSeeText('magyar nyelvű nyelvtani elemzés');
        $response->assertSeeText('Párhuzamos olvasás');
    }
}
