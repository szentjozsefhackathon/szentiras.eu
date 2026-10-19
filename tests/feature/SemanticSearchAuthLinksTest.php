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
        $response = $this->get('/ai-search');

        $response->assertStatus(200);
        $response->assertSee('href="/login?r=%2Fai-search"', false);
        $response->assertSee('href="/register?r=%2Fai-search"', false);
        $response->assertDontSee('r=%2Fai-search%2Fsearch', false);
    }
}
