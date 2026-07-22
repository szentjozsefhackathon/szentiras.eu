<?php

namespace SzentirasHu\Test\Search;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The Greek search page and the Greek search API run on the same service, so this pins down
 * that the page still finds the Greek verses and shows them with their Hungarian text.
 */
class GreekSearchPageTest extends FastDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['settings.enabledTranslations' => [1001, 1002]]);

        Cache::flush();
    }

    /**
     * A Greek verse together with the Hungarian verse of the same place; type 901 is the
     * verse type the Greek text is aligned with.
     */
    private function createGreekVerseWithTranslation(int $chapter, int $verse, string $text, string $normalizations): void
    {
        $gepi = sprintf('%d%03d%03d00', 101, $chapter, $verse);

        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = $gepi;
        $greekVerse->usx_code = 'GEN';
        $greekVerse->chapter = $chapter;
        $greekVerse->verse = $verse;
        $greekVerse->text = $text;
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'ἀγάπη';
        $greekVerse->strong_transliterations = 'agapé';
        $greekVerse->strong_normalizations = $normalizations;
        $greekVerse->save();

        $hungarianVerse = new Verse();
        $hungarianVerse->trans = 1001;
        $hungarianVerse->gepi = $gepi;
        $hungarianVerse->usx_code = 'GEN';
        $hungarianVerse->book_id = 99101;
        $hungarianVerse->chapter = $chapter;
        $hungarianVerse->numv = $verse;
        $hungarianVerse->tip = 901;
        $hungarianVerse->verse = "magyar {$gepi}";
        $hungarianVerse->verseroot = "verseroot{$gepi}";
        $hungarianVerse->ido = '2025-02-05';
        $hungarianVerse->save();
    }

    public function test_greek_word_search_shows_the_greek_text_and_its_translation(): void
    {
        $this->createGreekVerseWithTranslation(4, 1, 'ἡ ἀγάπη', 'agape');

        $this->post('/kereses/greekSearch', ['greekTranslit' => 'agape', 'mode' => 'lemma', 'rule' => 'all'])
            ->assertStatus(200)
            ->assertSee('ἀγάπη', false)
            ->assertSee('magyar 10100400100');
    }

    public function test_greek_word_search_with_any_rule_finds_either_word(): void
    {
        $this->createGreekVerseWithTranslation(4, 2, 'ἡ ἀγάπη', 'agape');

        $this->post('/kereses/greekSearch', ['greekTranslit' => 'agape pistis', 'mode' => 'lemma', 'rule' => 'any'])
            ->assertStatus(200)
            ->assertSee('magyar 10100400200');
    }

    public function test_greek_word_search_with_all_rule_requires_every_word(): void
    {
        $this->createGreekVerseWithTranslation(4, 3, 'ἡ ἀγάπη', 'agape');

        $this->post('/kereses/greekSearch', ['greekTranslit' => 'agape pistis', 'mode' => 'lemma', 'rule' => 'all'])
            ->assertStatus(200)
            ->assertDontSee('magyar 10100400300');
    }

    public function test_greek_word_search_narrowed_to_another_book_finds_nothing(): void
    {
        $this->createGreekVerseWithTranslation(4, 4, 'ἡ ἀγάπη', 'agape');

        $this->post('/kereses/greekSearch', ['greekTranslit' => 'agape', 'mode' => 'lemma', 'rule' => 'all', 'book' => 'JHN'])
            ->assertStatus(200)
            ->assertDontSee('magyar 10100400400');
    }

    public function test_greek_search_without_a_search_text_renders_the_form(): void
    {
        $this->post('/kereses/greekSearch')->assertStatus(200);
    }
}
