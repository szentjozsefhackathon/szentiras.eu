<?php

namespace SzentirasHu\Test;

use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The factory's passage is the seeded test text: TESTTRANS "Ter 2", whose verse 3 also exists
 * in TESTTRANS2 as "1Móz 2,3".
 */
class FamousPassagePageTest extends FastDatabaseTestCase
{
    public function test_page_shows_the_names_the_text_and_the_explanation(): void
    {
        FamousPassage::factory()->create();

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('<title>A hetedik nap – Isten megpihen – szöveg, magyarázat, fordítások</title>', false)
            ->assertSee('<meta name="description" content="A teremtés elbeszélésének zárása', false)
            ->assertSee('<link rel="canonical" href="'.url('/szakasz/a-hetedik-nap').'" />', false)
            ->assertSee('<h1 class="h2 mb-1">A hetedik nap</h1>', false)
            ->assertSee('Más néven: Isten megpihen')
            ->assertSee('id="v_100100200300"', false)
            ->assertSee('<h2>Összefüggés</h2>', false);
    }

    public function test_key_verse_is_shown_in_every_translation_that_has_the_book(): void
    {
        FamousPassage::factory()->create();

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('Ter 2,3 különböző fordításokban')
            ->assertSee('<a href="/TESTTRANS/Ter2" class="small">Translation Name 1 (TESTTRANS)', false)
            ->assertSee('<a href="/TESTTRANS2/1Móz2" class="small">Translation Name 2 (TESTTRANS2)', false);
    }

    public function test_seo_title_and_description_override_the_defaults(): void
    {
        FamousPassage::factory()->create(['seo_title' => 'Saját cím', 'seo_description' => 'Saját leírás']);

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('<title>Saját cím</title>', false)
            ->assertSee('<meta name="description" content="Saját leírás">', false);
    }

    public function test_draft_is_hidden_from_visitors_and_shown_to_editors(): void
    {
        FamousPassage::factory()->inactive()->create();

        $this->get('/szakasz/a-hetedik-nap')->assertNotFound();

        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('Ez az oldal még nem nyilvános');
    }

    public function test_list_shows_active_passages_in_order(): void
    {
        FamousPassage::factory()->create(['slug' => 'masodik', 'name' => 'Második rész', 'position' => 2]);
        FamousPassage::factory()->create(['slug' => 'elso', 'name' => 'Első rész', 'position' => 1]);
        FamousPassage::factory()->inactive()->create(['slug' => 'vazlat', 'name' => 'Vázlat rész', 'position' => 0]);

        $this->get('/szakasz')
            ->assertOk()
            ->assertSeeInOrder(['Első rész', 'Második rész'])
            ->assertDontSee('Vázlat rész');
    }

    public function test_chapter_page_links_to_its_famous_passage(): void
    {
        FamousPassage::factory()->create();

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<a href="'.url('/szakasz/a-hetedik-nap').'"><i class="bi bi-bookmark-star"></i> A hetedik nap – Isten megpihen (Ter 2): magyarázat és fordítások</a>', false);

        $this->get('/TESTTRANS/Ter50')
            ->assertOk()
            ->assertDontSee('magyarázat és fordítások</a>', false);
    }

    public function test_chapter_page_does_not_link_to_drafts(): void
    {
        FamousPassage::factory()->inactive()->create();

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertDontSee('magyarázat és fordítások</a>', false);
    }

    public function test_sitemap_lists_active_passages(): void
    {
        FamousPassage::factory()->create();
        FamousPassage::factory()->inactive()->create(['slug' => 'vazlat']);

        $this->get('/sitemap-guides.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/szakasz').'</loc>', false)
            ->assertSee('<loc>'.url('/szakasz/a-hetedik-nap').'</loc>', false)
            ->assertDontSee('/szakasz/vazlat', false);
    }
}
