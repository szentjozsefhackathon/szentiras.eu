<?php

namespace SzentirasHu\Test;

use SzentirasHu\Models\BookIntroduction;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class BookIntroductionDisplayTest extends FastDatabaseTestCase
{
    private const FIXTURE = __DIR__.'/../fixtures/book-introductions.xml';

    public function test_import_command_stores_the_introductions_of_the_translation(): void
    {
        $this->artisan('szentiras:import-book-introductions', ['translation' => 'TESTTRANS', '--file' => self::FIXTURE, '--no-purge' => true])
            ->expectsOutputToContain('5 bevezető betöltve (1 könyv-, 4 csoportbevezető)')
            ->assertSuccessful();

        $this->assertSame(5, BookIntroduction::query()->where('translation_id', 1001)->count());
        $this->assertSame(2, BookIntroduction::query()->forBook(1001, 'GEN')->count());
    }

    public function test_reimport_replaces_the_previous_introductions(): void
    {
        BookIntroduction::factory()->create(['title' => 'Régi bevezető']);

        $this->artisan('szentiras:import-book-introductions', ['translation' => 'TESTTRANS', '--file' => self::FIXTURE, '--no-purge' => true])
            ->assertSuccessful();

        $this->assertSame(5, BookIntroduction::query()->where('translation_id', 1001)->count());
        $this->assertFalse(BookIntroduction::query()->where('title', 'Régi bevezető')->exists());
    }

    public function test_import_command_rejects_an_unknown_translation(): void
    {
        $this->artisan('szentiras:import-book-introductions', ['translation' => 'NINCS', '--file' => self::FIXTURE, '--no-purge' => true])
            ->expectsOutputToContain('Ismeretlen fordítás: NINCS')
            ->assertFailed();
    }

    public function test_book_page_shows_the_group_and_the_book_introduction(): void
    {
        BookIntroduction::factory()->group(['GEN', 'EXO'])->create(['position' => 1]);
        BookIntroduction::factory()->create([
            'position' => 2,
            'title' => null,
            'paragraphs' => [
                ['style' => 'heading', 'segments' => [['text' => 'Az ősatyák']]],
                ['style' => 'paragraph', 'segments' => [
                    ['text' => 'A teremtés '],
                    ['text' => 'kezdetén', 'italic' => true],
                    ['text' => ' (vö. '],
                    ['text' => 'Kiv 20', 'reference' => 'Kiv 20'],
                    ['text' => ').'],
                ]],
            ],
        ]);

        $response = $this->get('/TESTTRANS/Ter');

        $response->assertOk();
        $response->assertSeeInOrder([
            '<section id="bevezetes"',
            '<summary class="fw-semibold">Bevezetés a Törvény öt könyvéhez</summary>',
            '<summary><h2 class="h5 d-inline">Bevezetés a könyvhöz</h2></summary>',
            '<h3 class="h6 mt-3">Az ősatyák</h3>',
        ], false);
        $response->assertSee('A teremtés <em>kezdetén</em> (vö. <a href="/TESTTRANS/Kiv20">Kiv 20</a>).', false);
        $this->assertSame(2, substr_count($response->getContent(), '<details class="mb-3">'));
        $response->assertDontSee('<details open', false);
    }

    public function test_book_page_without_introduction_has_no_introduction_section(): void
    {
        $response = $this->get('/TESTTRANS/Kiv');

        $response->assertOk();
        $response->assertDontSee('id="bevezetes"', false);
    }

    public function test_introduction_text_is_escaped(): void
    {
        BookIntroduction::factory()->create([
            'paragraphs' => [['style' => 'paragraph', 'segments' => [['text' => '<script>alert(1)</script>']]]],
        ]);

        $response = $this->get('/TESTTRANS/Ter');

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_chapter_page_links_to_the_book_introduction(): void
    {
        BookIntroduction::factory()->create();

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<a href="/TESTTRANS/Ter#bevezetes">', false);

        $this->get('/TESTTRANS/Kiv1')
            ->assertDontSee('#bevezetes', false);
    }
}
