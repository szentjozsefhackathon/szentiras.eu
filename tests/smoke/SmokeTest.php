<?php

namespace SzentirasHu\Test\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SzentirasHu\Service\LectureDownloader;
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

 
    public function setUp() : void
    {
        parent::setUp();

        /* Clean up caches, to not be affected by runtime */
       
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        $downloader = Mockery::mock(LectureDownloader::class);
        $this->app->instance(LectureDownloader::class, $downloader);
        $downloader->shouldReceive('getReferenceString')->andReturn('Ter 4,5; Kiv 3,4');

        // $textService = Mockery::mock(TextService::class);
        // $this->app->instance(TextService::class, $textService);
        // $textService->shouldReceive('getTeaser')->andReturn('teaser mock');
        // $textService->shouldReceive('getTranslatedVerses')->andReturn([new VerseContainer(null,null)]);

        $searcherFactory = Mockery::mock(SearcherFactory::class);
        $this->app->instance(SearcherFactory::class, $searcherFactory);
        $searcherFactory->shouldReceive('createSearcherFor')->andReturn(new SearcherStub());

        \Config::set('translations', 
            array_merge_recursive(\Config::get('translations'),
            ['TESTTRANS' => [
                'verseTypes' =>
                [
                    'text' => [6, 901],
                    'heading' => [5=>0, 10=>1, 20=>2, 30=>3],
                    'footnote' => [120, 2001, 2002],
                    'poemLine' => [902],
                    'xref' => [920]
                ],
                'textSource' => env('TEXT_SOURCE_KNB'),
                'id' => 1001]
                ]
            ));
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

    public function testBasicApi()
    {
        $this->get('/api/idezet/Ter 2,3')->assertStatus(200);
    }

    public function testBasicApiTranslation()
    {
        $this->get('/api/forditasok/10100100200')->assertStatus(200);
    }

    public function testBasicSearch()
    {
        $this->post('/kereses/search?textToSearch=Ter&book=all&translation=0&grouping=chapter')->assertStatus(200);
    }

    public function testBookWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter')->assertStatus(200);
    }

    public function testChapterWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter2')->assertStatus(200);
    }

    public function testRefWithExplicitTranslation() {
        $this->get('/TESTTRANS/Ter2,3')->assertStatus(200);
    }

    public function testRefWithNonExistingTranslation() {
        $this->get('/TESTTRANS/Ter2,123')->assertStatus(404);
    }


}