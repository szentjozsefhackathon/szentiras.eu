<?php

namespace SzentirasHu\Test\Mcp;

use Illuminate\Support\Facades\Cache;
use Mockery;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Mcp\Servers\BibleServer;
use SzentirasHu\Mcp\Tools\SearchGreekTool;
use SzentirasHu\Mcp\Tools\SearchVersesTool;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Search\FullTextSearchParams;
use SzentirasHu\Service\Search\FullTextSearchResult;
use SzentirasHu\Service\Search\Searcher;
use SzentirasHu\Service\Search\SearcherFactory;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The search tools let an agent find a passage it does not know the reference of. Because
 * every returned verse costs the client tokens, these tests pin down that the answer stays
 * compact, that the limit is honoured, and that the agent is told when it is only seeing
 * part of the hits.
 */
class SearchMcpToolTest extends FastDatabaseTestCase
{
    private ?FullTextSearchParams $capturedParams = null;

    protected function setUp(): void
    {
        parent::setUp();

        Translation::where('id', 1001)->update(['denom' => 'katolikus']);
        Translation::where('id', 1002)->update(['denom' => 'protestáns']);

        config(['settings.enabledTranslations' => [1001, 1002]]);

        Cache::flush();
    }

    /**
     * Sphinx is not available in tests, so the full text searcher is replaced by one that
     * reports the given verses as hits, honouring the limit it is given.
     *
     * @param  array<int, Verse>  $verses
     */
    private function fakeFullTextSearch(array $verses, int $totalHitCount): void
    {
        $searcher = Mockery::mock(Searcher::class);
        $searcher->shouldReceive('getExcerpts')->andReturn([]);
        $searcher->shouldReceive('get')->andReturnUsing(function () use ($verses, $totalHitCount): ?FullTextSearchResult {
            $result = new FullTextSearchResult();

            if ($this->capturedParams->countOnly) {
                $result->hitCount = $totalHitCount;

                return $result;
            }

            $result->verseIds = [];
            $result->verses = [];

            foreach (array_slice($verses, 0, $this->capturedParams->limit) as $verse) {
                $result->verseIds[] = $verse->id;
                $result->verses[$verse->id] = [
                    'id' => $verse->id,
                    'trans' => $verse->trans,
                    'usx_code' => $verse->usx_code,
                    'chapter' => $verse->chapter,
                    'numv' => $verse->numv,
                    'gepi' => $verse->gepi,
                    'tip' => $verse->tip,
                    'weight()' => 1,
                ];
            }

            $result->hitCount = count($result->verseIds);

            return $result->verseIds === [] ? null : $result;
        });

        $searcherFactory = Mockery::mock(SearcherFactory::class);
        $searcherFactory->shouldReceive('createSearcherFor')->andReturnUsing(function (FullTextSearchParams $params) use ($searcher) {
            $this->capturedParams = $params;

            return $searcher;
        });

        $this->app->instance(SearcherFactory::class, $searcherFactory);
    }

    /**
     * @return array<int, Verse>
     */
    private function seededVerses(): array
    {
        return Verse::where('trans', 1001)->where('usx_code', 'GEN')->orderBy('id')->get()->all();
    }

    public function test_search_returns_references_and_text_only(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        $response = BibleServer::tool(SearchVersesTool::class, ['text' => 'verse'])->assertOk();

        $response->assertSee('"reference":"Ter 2,3"');
        $response->assertSee('"text":"verse 100100200300"');
        // The word by word data of the search page would only cost the client tokens.
        $response->assertDontSee('resultsByBookNumber');
        $response->assertDontSee('greekWords');
    }

    public function test_search_reports_the_total_number_of_hits_and_truncation(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 42);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse', 'limit' => 1])
            ->assertOk()
            ->assertSee('"totalHitCount":42')
            ->assertSee('"truncated":true');
    }

    public function test_search_honours_the_limit(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse', 'limit' => 1])
            ->assertOk()
            ->assertSee('"reference":"Ter 2,3"')
            ->assertDontSee('"reference":"Ter 2,4"');
    }

    public function test_search_defaults_to_a_small_limit(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse'])->assertOk();

        $this->assertSame(10, $this->capturedParams->limit);
    }

    public function test_search_rejects_a_limit_above_the_maximum(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse', 'limit' => 500])
            ->assertHasErrors();
    }

    public function test_search_runs_in_the_translation_of_the_endpoint(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse'])
            ->assertOk()
            ->assertSee('"translation":"TESTTRANS"');

        $this->assertSame(1001, $this->capturedParams->translationId);
    }

    public function test_search_narrows_to_the_given_book(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse', 'book' => 'Jn'])->assertOk();

        $this->assertSame(['JHN'], array_keys($this->capturedParams->usxCodes));
    }

    public function test_search_rejects_an_unknown_book(): void
    {
        $this->fakeFullTextSearch($this->seededVerses(), 3);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'verse', 'book' => 'nincsilyen'])
            ->assertHasErrors()
            ->assertSee('Unknown book');
    }

    public function test_search_without_hits_answers_with_no_verses(): void
    {
        $this->fakeFullTextSearch([], 0);

        BibleServer::tool(SearchVersesTool::class, ['text' => 'nincsilyen'])
            ->assertOk()
            ->assertSee('"totalHitCount":0')
            ->assertSee('"verses":[]');
    }

    /**
     * A Greek verse together with the Hungarian verse of the same place; type 901 is the
     * verse type the Greek text is aligned with.
     */
    private function createGreekVerseWithTranslation(int $chapter, int $verse, string $text): GreekVerse
    {
        $gepi = sprintf('%d%03d%03d00', 101, $chapter, $verse);

        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = $gepi;
        $greekVerse->usx_code = 'GEN';
        $greekVerse->chapter = $chapter;
        $greekVerse->verse = $verse;
        $greekVerse->text = $text;
        $greekVerse->json = '[]';
        $greekVerse->strongs = 'ἀγάπη';
        $greekVerse->strong_transliterations = 'agapé';
        $greekVerse->strong_normalizations = 'agape';
        $greekVerse->save();

        $hungarianVerse = new Verse();
        $hungarianVerse->trans = 1001;
        $hungarianVerse->gepi = $gepi;
        $hungarianVerse->usx_code = 'GEN';
        $hungarianVerse->book_id = 99101;
        $hungarianVerse->chapter = $chapter;
        $hungarianVerse->numv = $verse;
        $hungarianVerse->tip = 901;
        $hungarianVerse->verse = "magyar {$gepi}";
        $hungarianVerse->verseroot = "verseroot{$gepi}";
        $hungarianVerse->ido = '2025-02-05';
        $hungarianVerse->save();

        return $greekVerse;
    }

    public function test_greek_search_returns_the_greek_text_with_its_translation(): void
    {
        $this->createGreekVerseWithTranslation(4, 1, 'ἡ ἀγάπη');

        BibleServer::tool(SearchGreekTool::class, ['text' => 'agape'])
            ->assertOk()
            ->assertSee('"reference":"Ter 4,1"')
            ->assertSee('"greekText":"ἡ ἀγάπη"')
            ->assertSee('"text":"magyar 101004001')
            // The word by word analysis belongs to get-greek-verses, not to a search answer.
            ->assertDontSee('morphology');
    }

    public function test_greek_search_honours_the_limit_and_reports_the_total(): void
    {
        $this->createGreekVerseWithTranslation(5, 1, 'ἀγάπη πρώτη');
        $this->createGreekVerseWithTranslation(5, 2, 'ἀγάπη δευτέρα');

        BibleServer::tool(SearchGreekTool::class, ['text' => 'agape', 'limit' => 1])
            ->assertOk()
            ->assertSee('"totalHitCount":2')
            ->assertSee('"truncated":true')
            ->assertSee('"reference":"Ter 5,1"')
            ->assertDontSee('"reference":"Ter 5,2"');
    }

    public function test_greek_search_without_hits_answers_with_no_verses(): void
    {
        $this->createGreekVerseWithTranslation(6, 1, 'ἡ ἀγάπη');

        BibleServer::tool(SearchGreekTool::class, ['text' => 'nincsilyen'])
            ->assertOk()
            ->assertSee('"totalHitCount":0')
            ->assertSee('"verses":[]');
    }

    public function test_greek_search_rejects_an_unknown_mode(): void
    {
        BibleServer::tool(SearchGreekTool::class, ['text' => 'agape', 'mode' => 'nincsilyen'])
            ->assertHasErrors();
    }
}
