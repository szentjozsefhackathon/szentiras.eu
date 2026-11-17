<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Test\Common\TestCase;

class VerseAnchorLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $currentConfig = \Config::get('translations');
        $currentConfig['definitions']['TESTTRANS'] = [
            'verseTypes' => [
                'text' => [6, 901],
                'heading' => [5 => 0, 10 => 1, 20 => 2, 30 => 3],
                'footnote' => [120, 2001, 2002],
                'poemLine' => [902],
                'xref' => [920],
            ],
            'textSource' => env('TEXT_SOURCE_KNB'),
            'id' => 1001,
        ];
        $currentConfig['ids'][1001] = 'TESTTRANS';
        \Config::set('translations', $currentConfig);
    }

    public function testVerseAnchorPointsToChapterFragmentNotASeparateVersePage(): void
    {
        $response = $this->get('/TESTTRANS/Ter2');

        $response->assertStatus(200);
        $response->assertSee('class="verse-anchor"', false);
        $response->assertSee('href="/TESTTRANS/Ter2#v_', false);
        $response->assertDontSee('class="verse-anchor" href="/TESTTRANS/Ter2,', false);
    }

    public function testChapterPageIsIndexableAndCanonicalToItself(): void
    {
        $this->app['env'] = 'production';
        \Config::set('page_cache.enabled', false);

        $response = $this->get('/TESTTRANS/Ter2');

        $response->assertStatus(200);
        $response->assertSee('<link rel="canonical" href="' . url('/TESTTRANS/Ter2') . '" />', false);
        $response->assertDontSee('name="robots" content="noindex', false);
    }

    public function testSingleVersePageIsNotIndexedAndCanonicalToChapter(): void
    {
        $this->app['env'] = 'production';
        \Config::set('page_cache.enabled', false);

        $response = $this->get('/TESTTRANS/Ter2,3');

        $response->assertStatus(200);
        $response->assertSee('<link rel="canonical" href="' . url('/TESTTRANS/Ter2') . '" />', false);
        $response->assertSee('name="robots" content="noindex,follow"', false);
    }
}
