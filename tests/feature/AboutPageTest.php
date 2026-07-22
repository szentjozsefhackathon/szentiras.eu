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

    public function test_informaciok_page_links_to_developers_page(): void
    {
        $response = $this->get('/informaciok');

        $response->assertOk();
        $response->assertSee('href="/api"', false);
        $response->assertSee('Fejlesztőknek');
        $response->assertSee('MCP szerver', false);
    }
}
