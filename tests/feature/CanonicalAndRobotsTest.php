<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Config;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * Every page tells search engines its one canonical URL; utility pages and fragments stay out of the index.
 */
class CanonicalAndRobotsTest extends FastDatabaseTestCase
{
    public function test_pages_without_their_own_rule_are_canonical_without_the_query_string(): void
    {
        $this->get('/?utm_source=newsletter')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/').'" />', false);

        $this->get('/forditasok?fbclid=abc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/forditasok').'" />', false);
    }

    public function test_translation_page_is_its_own_canonical(): void
    {
        $this->get('/TESTTRANS')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/TESTTRANS').'" />', false);
    }

    public function test_pages_with_their_own_canonical_have_exactly_one(): void
    {
        foreach (['/TESTTRANS/Ter2', '/TESTTRANS/Ter2,3', '/TESTTRANS/Ter', '/kereses?text=teszt', '/gorog-szotar?page=2'] as $url) {
            $content = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($content, 'rel="canonical"'), $url);
        }

        $this->get('/kereses?text=teszt')->assertSee('<link rel="canonical" href="'.url('/kereses').'" />', false);
    }

    public function test_verse_range_page_is_not_indexed_but_its_links_are_followed(): void
    {
        $this->app['env'] = 'production';
        Config::set('page_cache.enabled', false);

        $this->get('/TESTTRANS/Ter2,3-4')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/TESTTRANS/Ter2').'" />', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertDontSee('noindex,nofollow', false);
    }

    public function test_whole_chapter_page_is_indexable(): void
    {
        $this->app['env'] = 'production';
        Config::set('page_cache.enabled', false);

        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_utility_pages_send_a_noindex_header(): void
    {
        foreach (['/contact', '/register', '/login', '/gorog-szotar/filter?q=agape'] as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        }
    }

    public function test_content_pages_send_no_noindex_header(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/TESTTRANS/Ter2')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_media_with_an_invalid_id_is_not_found(): void
    {
        $this->get('/media/not-a-uuid')->assertNotFound();
    }

    public function test_home_page_links_point_to_the_default_translation(): void
    {
        Config::set('settings.defaultTranslationAbbrev', 'TESTTRANS');

        $this->get('/')
            ->assertOk()
            ->assertSee('href="/TESTTRANS/Mt"', false)
            ->assertSee('href="/TESTTRANS/Zsolt1"', false)
            ->assertDontSee('href="/Mt"', false);
    }
}
