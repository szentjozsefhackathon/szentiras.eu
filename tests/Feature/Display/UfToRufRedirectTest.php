<?php

namespace SzentirasHu\Test\Display;

use SzentirasHu\Test\Common\TestCase;

class UfToRufRedirectTest extends TestCase
{
    public function test_uf_translation_page_redirects_to_ruf(): void
    {
        $this->get('/UF')->assertStatus(301)->assertRedirect('/RUF');
    }

    public function test_uf_reference_redirects_to_ruf(): void
    {
        $this->get('/UF/Jn3,16')->assertStatus(301)->assertRedirect('/RUF/Jn3,16');
    }

    public function test_uf_reference_redirect_keeps_query_string(): void
    {
        $this->get('/UF/Jn3?foo=bar')->assertStatus(301)->assertRedirect('/RUF/Jn3?foo=bar');
    }

    public function test_ruf_is_not_redirected(): void
    {
        $response = $this->get('/RUF/Jn3');
        $this->assertNotSame('/RUF/Jn3', $response->headers->get('Location'));
        $this->assertNotSame(301, $response->getStatusCode());
    }

    public function test_lowercase_uf_redirects_to_ruf(): void
    {
        $this->get('/uf')->assertStatus(301)->assertRedirect('/RUF');
        $this->get('/uf/Jn3')->assertStatus(301)->assertRedirect('/RUF/Jn3');
    }
}
