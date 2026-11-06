<?php

namespace SzentirasHu\Test;

use SzentirasHu\Test\Common\FastDatabaseTestCase;

class SemanticSearchAuthLinksTest extends FastDatabaseTestCase
{
    /**
     * The results page is served from the POST-only /ai-search/search route,
     * so the login/register links must redirect back to the GET /ai-search page.
     */
    public function test_auth_links_redirect_to_get_route(): void
    {
        config(['settings.ai.unregisteredSearchLimit' => 5]);

        $response = $this->get('/ai-search');

        $response->assertStatus(200);
        $response->assertSee('legfeljebb 5 keresést');
        $response->assertSee('href="/login?r=%2Fai-search"', false);
        $response->assertSee('href="/register?r=%2Fai-search"', false);
        $response->assertDontSee('r=%2Fai-search%2Fsearch', false);
    }

    /**
     * Without a limit the search is open to every visitor, so the page does not ask to log in or register.
     */
    public function test_no_auth_links_without_a_limit(): void
    {
        config(['settings.ai.unregisteredSearchLimit' => -1]);

        $response = $this->get('/ai-search');

        $response->assertStatus(200);
        $response->assertSee('cf-turnstile', false);
        $response->assertDontSee('legfeljebb');
        $response->assertDontSee('lépj be</a>', false);
        $response->assertDontSee('regisztrálj</a>', false);
    }
}
