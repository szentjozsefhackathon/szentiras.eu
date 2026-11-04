<?php

namespace SzentirasHu\Test;

use SzentirasHu\Test\Common\FastDatabaseTestCase;

class HomeSearchTileTest extends FastDatabaseTestCase
{
    public function test_search_tile_describes_each_search_type(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Szavak, kifejezések vagy igehely (pl. Jn 3,16) a teljes Bibliában');
        $response->assertSeeInOrder(['Részletes keresés', 'fordítás és könyv szerint szűrve']);
        $response->assertSeeInOrder(['MI keresés', 'Pontos szöveg nélkül is', 'az Egyház tanítását is figyelembe véve']);
    }

    public function test_search_tile_shows_the_search_type_buttons_on_every_screen_size(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('d-none d-md-block text-center', false);
        $response->assertDontSee('title="MI keresés"', false);
    }

    public function test_news_announces_the_renewed_meaning_search(): void
    {
        $response = $this->get('/info');

        $response->assertOk();
        $response->assertSee('2026. 11. 04.');
        $response->assertSee('Megújult az <strong><a href="/ai-search">MI keresés</a></strong>', false);
        $response->assertSee('egyházi tanítást');
        $response->assertSee('nem emlékszel a pontos szövegre');
    }
}
