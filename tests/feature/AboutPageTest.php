<?php

namespace SzentirasHu\Test;

use SzentirasHu\Test\Common\TestCase;

class AboutPageTest extends TestCase
{
    public function test_about_page_is_accessible(): void
    {
        $response = $this->get('/rolunk');

        $response->assertOk();
        $response->assertSee('Az oldalról');
        $response->assertSee('Szentírás.eu');
    }

    public function test_about_page_renders_markdown_headings_as_html(): void
    {
        $response = $this->get('/rolunk');

        $response->assertSee('<h2>Keresés</h2>', false);
        $response->assertSee('<h2>Bibliafordítások</h2>', false);
    }

    public function test_informaciok_page_links_to_about_page(): void
    {
        $response = $this->get('/informaciok');

        $response->assertOk();
        $response->assertSee('href="/rolunk"', false);
    }

    public function test_about_page_states_the_licences(): void
    {
        $response = $this->get('/rolunk');

        $response->assertOk();
        $response->assertSee('a Szerzői Jogi Törvénynek megfelelően használhatók fel');
        $response->assertSee('CC BY-SA 4.0');
        $response->assertSee('tüntesd fel forrásként a');
    }

    public function test_informaciok_page_states_the_licences(): void
    {
        $response = $this->get('/informaciok');

        $response->assertOk();
        // The translations belong to their publishers, our own material is CC BY-SA.
        $response->assertSee('A honlapon megjelenő bibliafordítások a Szerzői Jogi Törvénynek megfelelően használhatók fel.');
        $response->assertSee('A Szentírás.eu saját anyagai (kommentárok, görög szószedet stb.)');
        $response->assertSee('CC BY-SA 4.0');
        $response->assertSee('tüntesd fel forrásként a');
    }

    public function test_informaciok_page_links_to_developers_page(): void
    {
        $response = $this->get('/informaciok');

        $response->assertOk();
        $response->assertSee('href="/api"', false);
        $response->assertSee('API és MCP szerver');
        $response->assertSee('MCP szerver', false);
    }
}
