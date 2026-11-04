<?php

namespace SzentirasHu\Test\Api;

use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use SzentirasHu\Service\Search\ExtendedSearchHeadingHit;
use SzentirasHu\Service\Search\ExtendedSearchHit;
use SzentirasHu\Service\Search\ExtendedSearchParams;
use SzentirasHu\Service\Search\ExtendedSearchResult;
use SzentirasHu\Service\Search\ExtendedSphinxSearcher;
use SzentirasHu\Service\Search\SearcherFactory;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The extended search answers with the bare hits and their weights, so that a caller such as
 * Magister can fuse them with its own ranking. These tests pin down the shape of the answer
 * and that every option reaches the search.
 */
class ExtendedSearchApiTest extends FastDatabaseTestCase
{
    /**
     * The parameters the last search ran with, captured from the mocked searcher.
     */
    private ?ExtendedSearchParams $capturedParams = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['settings.enabledTranslations' => [1001, 1002]]);
        config(['api.key_required' => false]);

        Cache::flush();
    }

    /**
     * Sphinx is not available in tests, so the searcher is replaced by one that answers with
     * the given result and records what it was asked for.
     */
    private function fakeExtendedSearch(?ExtendedSearchResult $result = null): void
    {
        $searcher = Mockery::mock(ExtendedSphinxSearcher::class);
        $searcher->shouldReceive('get')->andReturn($result ?? new ExtendedSearchResult([], 0));

        $searcherFactory = Mockery::mock(SearcherFactory::class);
        $searcherFactory->shouldReceive('createExtendedSearcherFor')->andReturnUsing(function (ExtendedSearchParams $params) use ($searcher) {
            $this->capturedParams = $params;

            return $searcher;
        });

        $this->app->instance(SearcherFactory::class, $searcherFactory);
    }

    public function test_hits_are_returned_compactly_with_their_weights_word_factors_and_the_total(): void
    {
        $this->fakeExtendedSearch(new ExtendedSearchResult([
            new ExtendedSearchHit('GEN', 1, 1, 1001, 42938.5, longestRun: 8, wordCount: 5, exactHit: true),
            new ExtendedSearchHit('JHN', 1, 3, 1002, 1200.0, longestRun: 1, wordCount: 2),
        ], 62, queryWordCount: 5, questionWordCount: 8));

        $this->getJson('/api/search-extended/kezdetben')
            ->assertOk()
            ->assertExactJson([
                'extendedSearchResult' => [
                    'totalHitCount' => 62,
                    'queryWordCount' => 5,
                    'questionWordCount' => 8,
                    'hits' => [
                        ['usx' => 'GEN', 'chapter' => 1, 'verse' => 1, 'translation' => 'TESTTRANS', 'weight' => 42938.5, 'lcs' => 8, 'wordCount' => 5, 'exactHit' => true],
                        ['usx' => 'JHN', 'chapter' => 1, 'verse' => 3, 'translation' => 'TESTTRANS2', 'weight' => 1200.0, 'lcs' => 1, 'wordCount' => 2, 'exactHit' => false],
                    ],
                    'headingHitCount' => 0,
                    'headings' => [],
                ],
            ]);
    }

    public function test_a_heading_no_verse_holds_comes_back_apart_from_the_verses(): void
    {
        $this->fakeExtendedSearch(new ExtendedSearchResult(
            [new ExtendedSearchHit('MAT', 11, 27, 1001, 226314.2, longestRun: 1, wordCount: 1)],
            146,
            queryWordCount: 2,
            questionWordCount: 2,
            headings: [
                new ExtendedSearchHeadingHit(new ExtendedSearchHit('LUK', 15, 11, 1001, 644465.9, longestRun: 2, wordCount: 2), 'A tékozló fiú.'),
                new ExtendedSearchHeadingHit(new ExtendedSearchHit('LUK', 15, 11, 1002, 644465.9, longestRun: 2, wordCount: 2), 'A tékozló fiú'),
            ],
            headingHitCount: 17,
        ));

        $this->getJson('/api/search-extended/tékozló fiú?quorum=0.6')
            ->assertOk()
            ->assertJsonPath('extendedSearchResult.totalHitCount', 146)
            ->assertJsonPath('extendedSearchResult.headingHitCount', 17)
            ->assertJsonCount(1, 'extendedSearchResult.hits')
            ->assertJsonPath('extendedSearchResult.headings', [
                ['usx' => 'LUK', 'chapter' => 15, 'verse' => 11, 'translation' => 'TESTTRANS', 'weight' => 644465.9, 'lcs' => 2, 'wordCount' => 2, 'exactHit' => false, 'text' => 'A tékozló fiú.'],
                ['usx' => 'LUK', 'chapter' => 15, 'verse' => 11, 'translation' => 'TESTTRANS2', 'weight' => 644465.9, 'lcs' => 2, 'wordCount' => 2, 'exactHit' => false, 'text' => 'A tékozló fiú'],
            ]);
    }

    public function test_a_search_both_a_heading_and_verses_hold_returns_each_in_its_own_list(): void
    {
        $this->fakeExtendedSearch(new ExtendedSearchResult(
            [new ExtendedSearchHit('LUK', 10, 33, 1001, 640000.0, longestRun: 2, wordCount: 2)],
            3,
            queryWordCount: 2,
            questionWordCount: 2,
            headings: [new ExtendedSearchHeadingHit(new ExtendedSearchHit('LUK', 10, 25, 1001, 642751.1, longestRun: 2, wordCount: 2, exactHit: true), 'Az irgalmas szamaritánus.')],
            headingHitCount: 1,
        ));

        $response = $this->getJson('/api/search-extended/irgalmas szamaritánus')->assertOk();

        $response->assertJsonPath('extendedSearchResult.hits.0.verse', 33);
        $response->assertJsonMissingPath('extendedSearchResult.hits.0.text');
        $response->assertJsonPath('extendedSearchResult.headings.0.verse', 25);
        $response->assertJsonPath('extendedSearchResult.headings.0.exactHit', true);
        $response->assertJsonPath('extendedSearchResult.headings.0.text', 'Az irgalmas szamaritánus.');
    }

    public function test_search_without_hits_answers_with_an_empty_result(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/nincsilyen')
            ->assertOk()
            ->assertExactJson(['extendedSearchResult' => ['totalHitCount' => 0, 'queryWordCount' => 0, 'questionWordCount' => 0, 'hits' => [], 'headingHitCount' => 0, 'headings' => []]]);
    }

    public function test_every_enabled_translation_is_searched_by_default(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse')->assertOk();

        $this->assertEqualsCanonicalizing([1001, 1002], $this->capturedParams->translationIds);
        $this->assertSame([], $this->capturedParams->usxCodes);
        $this->assertNull($this->capturedParams->quorum);
        $this->assertFalse($this->capturedParams->onePerVerse);
    }

    public function test_default_translation_is_preferred_among_equal_hits(): void
    {
        config(['settings.defaultTranslationAbbrev' => 'TESTTRANS2']);
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse?onePerVerse=true')->assertOk();

        $this->assertSame([1002, 1001], $this->capturedParams->translationIds);
    }

    public function test_search_narrows_to_the_given_translation(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse/TESTTRANS2')->assertOk();

        $this->assertSame([1002], $this->capturedParams->translationIds);
    }

    public function test_unknown_translation_is_rejected(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse/NINCSILYEN')->assertNotFound();
    }

    public function test_options_reach_the_search(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse?book=Jn&limit=20&quorum=0.6&onePerVerse=true')->assertOk();

        $this->assertSame(['JHN'], array_keys($this->capturedParams->usxCodes));
        $this->assertSame(20, $this->capturedParams->limit);
        $this->assertSame(0.6, $this->capturedParams->quorum);
        $this->assertTrue($this->capturedParams->onePerVerse);
    }

    public function test_quorum_of_one_is_accepted(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse?quorum=1')->assertOk();

        $this->assertSame(1.0, $this->capturedParams->quorum);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQuorums(): array
    {
        return [
            'zero' => ['0'],
            'above one' => ['1.5'],
            'negative' => ['-0.5'],
            'not a number' => ['sok'],
        ];
    }

    #[DataProvider('invalidQuorums')]
    public function test_invalid_quorum_is_rejected(string $quorum): void
    {
        $this->fakeExtendedSearch();

        $this->getJson("/api/search-extended/verse?quorum={$quorum}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('quorum');
    }

    public function test_invalid_options_are_rejected(): void
    {
        $this->fakeExtendedSearch();

        $this->getJson('/api/search-extended/verse?onePerVerse=talan&book=nincsilyen&limit=100000')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['onePerVerse', 'book', 'limit']);
    }
}
