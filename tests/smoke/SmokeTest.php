<?php

namespace SzentirasHu\Test\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SzentirasHu\Service\Search\SearcherFactory;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Test\Common\TestCase;

use Illuminate\Support\Facades\Artisan;

/* To run the app in your environment, run it using
php artisan serve --port 1024 --env=testing
*/
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function setUp() : void
    {
        parent::setUp();

        /* Clean up caches, to not be affected by runtime */
       
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // Seed the database with test data
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        // $textService = Mockery::mock(TextService::class);
        // $this->app->instance(TextService::class, $textService);
        // $textService->shouldReceive('getTeaser')->andReturn('teaser mock');
        // $textService->shouldReceive('getTranslatedVerses')->andReturn([new VerseContainer(null,null)]);

        $searcherFactory = Mockery::mock(SearcherFactory::class);
        $this->app->instance(SearcherFactory::class, $searcherFactory);
        $searcherFactory->shouldReceive('createSearcherFor')->andReturn(new SearcherStub());

        $currentConfig = \Config::get('translations');
        $currentConfig['definitions']['TESTTRANS'] = [
                'verseTypes' =>
                [
                    'text' => [6, 901],
                    'heading' => [5=>0, 10=>1, 20=>2, 30=>3],
                    'footnote' => [120, 2001, 2002],
                    'poemLine' => [902],
                    'xref' => [920]
                ],
                'textSource' => env('TEXT_SOURCE_KNB'),
                'id' => 1001];
        $currentConfig['ids'][1001] = 'TESTTRANS' ;
        \Config::set('translations', $currentConfig);

    }


    /**
     * Basic home page test.
     *
     * @return void
     */
    public function testBasicHomePage()
    {
        $this->get('/')->assertStatus(200);
    }

    public function testBasicTranslationPage()
    {
        $this->get('/TESTTRANS')->assertStatus(200);
    }

    public function testTranslationPageHasH1()
    {
        $response = $this->get('/TESTTRANS');
        $response->assertStatus(200);
        // Every page needs an <h1> for SEO (Bing crawler reports missing headers).
        $response->assertSee('<h1 class="h4">', false);
    }

    public function testTranslationPageHasDescriptiveMetaDescription()
    {
        $description = $this->metaDescription($this->get('/TESTTRANS')->getContent());
        // Bing reports meta descriptions shorter than ~150 chars as too short.
        $this->assertGreaterThanOrEqual(150, mb_strlen($description));
        $this->assertStringContainsString('Translation Name 1', $description);
    }

    public function testBasicApi()
    {
        $this->get('/api/idezet/Ter 2,3')->assertStatus(200);
    }

    public function testDeveloperPageDocumentsTheMcpServer()
    {
        $response = $this->get('/api');

        $response->assertStatus(200);
        $response->assertSee('MCP szerver');
        $response->assertSee(url('/mcp/bible'), false);
        $response->assertSee('get-verses');
        $response->assertSee('search-verses');
        $response->assertSee('list-translations');
        $response->assertSee('get-greek-verses');
        $response->assertSee('search-greek');
        $response->assertSee('lookup-greek-word');
    }

    public function testDeveloperPageDocumentsMcpHeaderAuthenticationFirst()
    {
        $response = $this->get('/api');

        // Most MCP clients take the token in a header; the query parameter is only a fallback.
        $response->assertSee('"X-API-Key": "AZ_EN_API_KULCSOM"', false);
        $response->assertSee(url('/mcp/bible/RUF').'?api_key=AZ_EN_API_KULCSOM', false);
    }

    public function testDeveloperPageDocumentsSelfServiceApiKeys()
    {
        $response = $this->get('/api');

        $response->assertStatus(200);
        $response->assertSee('önkiszolgáló', false);
        $response->assertSee(url('/profile/api-keys'), false);
    }

    public function testDeveloperPageHasHeaderAndMetaDescription()
    {
        $response = $this->get('/api');

        // Every page needs an <h1> for SEO (Bing crawler reports missing headers).
        $response->assertSee('<h1 class="h4">API és MCP szerver</h1>', false);
        // Bing reports meta descriptions shorter than ~150 chars as too short.
        $this->assertGreaterThanOrEqual(150, mb_strlen($this->metaDescription($response->getContent())));
    }

    public function testDeveloperPageShowsCurlExamplesForEveryRestEndpoint()
    {
        $response = $this->get('/api');

        // The endpoints cannot be tried out from the browser without an API key,
        // so every example is a runnable curl command instead of a link.
        foreach (['idezet/1Kor13,10-13', 'forditasok', 'forditasok/JHN_13_34', 'books', 'books/KG', 'ref/1Kor2,2-3.4;Jn1,1-2', 'search/szeretet', 'search/szeretet/SZIT', 'greek-search/agape'] as $endpoint) {
            $response->assertSee('curl -H "X-API-Key: AZ_EN_API_KULCSOM" "'.url('api/'.$endpoint).'"', false);
        }
    }

    public function testDeveloperPageStatesTheApiKeyIsRequiredWithoutGracePeriod()
    {
        $response = $this->get('/api');

        $response->assertSee('Minden API- és MCP-hívás érvényes API kulcsot igényel.', false);
        $response->assertDontSee('2026', false);
        $response->assertDontSee('Grace', false);
    }

    public function testBasicApiTranslation()
    {
        $this->get('/api/forditasok/10100100200')->assertStatus(200);
    }

    public function testBasicSearch()
    {
        $this->post('/kereses/search?textToSearch=Ter&book=all&translation=0&grouping=chapter')->assertStatus(200);
    }

    public function testSearchPageShowsHungarianGuideByDefault()
    {
        $response = $this->get('/kereses');
        $response->assertStatus(200);
        $response->assertSee('<div id="searchInfoHun" class="">', false);
        $response->assertSee('<div id="searchInfoGrc" class="d-none">', false);
        $response->assertSee('Az igehelyekre a', false);
    }

    public function testSearchPageShowsGreekGuideOnGreekTab()
    {
        $response = $this->get('/kereses?greek=1');
        $response->assertStatus(200);
        $response->assertSee('<div id="searchInfoHun" class="d-none">', false);
        $response->assertSee('<div id="searchInfoGrc" class="">', false);
        $response->assertSee('Strong-szavai', false);
    }

    public function testBookWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter')->assertStatus(200);
    }

    public function testBookPageHasH1() {
        $response = $this->get('/TESTTRANS/Ter');
        $response->assertStatus(200);
        // Every page needs an <h1> for SEO (Bing crawler reports missing headers).
        $response->assertSee('<h1 class="h4">', false);
    }

    public function testBookPageHasDescriptiveMetaDescription() {
        $description = $this->metaDescription($this->get('/TESTTRANS/Ter')->getContent());
        // The book description leads with the book and translation names rather
        // than falling back to the generic site-wide description (Bing reports
        // those as too short / duplicate).
        $this->assertStringContainsString('Ter.', $description);
        $this->assertStringContainsString('Translation Name 1', $description);
    }

    public function testChapterWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter2')->assertStatus(200);
    }

    public function testChapterPageHasSeoTitleAndH1() {
        $response = $this->get('/TESTTRANS/Ter2');
        $response->assertStatus(200);
        // Canonical chapter pages use the full book and chapter names consistently
        // in the title and visible heading so search engines have a clear title.
        $response->assertSee('<title>Ter. – 2. fejezet – Translation Name 1</title>', false);
        $response->assertSee('<h1 class="h4">Ter. – 2. fejezet</h1>', false);
    }

    public function testPsalmPageUsesPsalmInSeoTitleAndH1(): void
    {
        \SzentirasHu\Data\Entity\Book::query()
            ->whereKey(99101)
            ->update([
                'name' => 'A Zsoltárok könyve',
                'abbrev' => 'Zsolt',
                'usx_code' => 'PSA',
            ]);
        \SzentirasHu\Data\Entity\Verse::query()
            ->where('book_id', 99101)
            ->update(['usx_code' => 'PSA']);

        $response = $this->get('/TESTTRANS/Zsolt2');

        $response->assertStatus(200);
        $response->assertSee('<title>Zsoltárok könyve – 2. zsoltár – Translation Name 1</title>', false);
        $response->assertSee('<h1 class="h4">Zsoltárok könyve – 2. zsoltár</h1>', false);
    }

    public function testChapterPageHasDescriptiveMetaDescription() {
        $description = $this->metaDescription($this->get('/TESTTRANS/Ter2')->getContent());
        // The chapter description leads with the reference and translation name
        // so it is descriptive and unique even for short chapters, instead of a
        // single short verse (Bing reports those as too short).
        $this->assertStringContainsString('Ter 2 – Translation Name 1.', $description);
    }

    /**
     * Extracts the <meta name="description"> content from a rendered page.
     */
    private function metaDescription(string $html): string
    {
        if (preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES);
        }

        return "";
    }

    public function testVersesHaveReferenceAnchorLinks() {
        $response = $this->get('/TESTTRANS/Ter2');
        $response->assertStatus(200);
        // Verses anchor to a fragment on the chapter page rather than a separate
        // per-verse URL, so crawlers don't flood individual verse pages.
        $response->assertSee('href="/TESTTRANS/Ter2#v_', false);
        $response->assertSee('title="Ter 2,3"', false);
    }

    public function testRefWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter2,3')->assertStatus(200);
    }

    public function testRefWithNonExistingTranslation() {
        $this->get('/TESTTRANS/Ter2,123')->assertStatus(404);
    }

    public function testCanonicalVersePageMarksVariantLinksNofollow() {
        $response = $this->get('/TESTTRANS/Ter2,3');
        $response->assertStatus(200);
        // Crawlers must not follow links into the combinatorial variant URL space.
        $response->assertSee('id="fullContextButton" rel="nofollow"', false);
    }

    public function testFullContextVariantPageIsNofollow() {
        $response = $this->get('/TESTTRANS/Ter2,3?fullContext');
        $response->assertStatus(200);
        $response->assertSee('content="noindex,nofollow"', false);
        $response->assertDontSee('content="noindex,follow"', false);
    }

    public function testAnonymousVersePageIsPubliclyCacheableAndCookieFree() {
        $response = $this->get('/TESTTRANS/Ter2,3');
        $response->assertStatus(200);
        // Anonymous text pages must be safe for Cloudflare to cache: no cookies, public Cache-Control.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=', $cacheControl);
        $response->assertHeaderMissing('Set-Cookie');
    }

    public function testVersePageDefersCommentariesToAjax() {
        $response = $this->get('/TESTTRANS/Ter2,3');
        $response->assertStatus(200);
        // The container placeholder is present so JS can load commentaries...
        $response->assertSee('class="commentary-container"', false);
        // ...but the commentary markup itself must not be baked into the cached page.
        $response->assertDontSee('id="commentary-panels-', false);
    }

    public function testCommentaryContentEndpointIsShortCacheableForAnonymous() {
        $response = $this->get('/api/commentaries/content?reference=Ter2&translation=TESTTRANS&containerIndex=0');
        $response->assertStatus(200);
        $response->assertSee('commentary-panels-0', false);
        // Anonymous visitors get a short shared cache so bot floods collapse to few origin hits.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $response->assertHeaderMissing('Set-Cookie');
    }

    public function testCommentaryContentEndpointUnknownTranslationIs404() {
        $this->get('/api/commentaries/content?reference=Ter2&translation=NOPE')->assertStatus(404);
    }

}
