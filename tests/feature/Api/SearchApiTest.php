<?php

namespace SzentirasHu\Test\Api;

use Illuminate\Support\Facades\Cache;
use Mockery;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Search\FullTextSearchParams;
use SzentirasHu\Service\Search\FullTextSearchResult;
use SzentirasHu\Service\Search\Searcher;
use SzentirasHu\Service\Search\SearcherFactory;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The search API exposes the same options the search form offers, so that a caller can
 * narrow a search down instead of paging through everything. These tests pin down that each
 * option reaches the search itself, and that the Greek search finds verses by their original
 * wording.
 */
class SearchApiTest extends FastDatabaseTestCase
{
    /**
     * The parameters the last search ran with, captured from the mocked searcher.
     */
    private ?FullTextSearchParams $capturedParams = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['settings.enabledTranslations' => [1001, 1002]]);
        config(['api.key_required' => false]);

        Cache::flush();
    }

    /**
     * Sphinx is not available in tests, so the full text searcher is replaced by one that
     * reports the given verses as hits and records what it was asked for.
     *
     * @param  array<int, Verse>  $verses
     */
    private function fakeFullTextSearch(array $verses = []): void
    {
        $result = new FullTextSearchResult();
        $result->verseIds = [];
        $result->verses = [];

        foreach ($verses as $verse) {
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

        $result->hitCount = count($verses);

        $searcher = Mockery::mock(Searcher::class);
        $searcher->shouldReceive('get')->andReturn($verses === [] ? null : $result);
        $searcher->shouldReceive('getExcerpts')->andReturn([]);

        $searcherFactory = Mockery::mock(SearcherFactory::class);
        $searcherFactory->shouldReceive('createSearcherFor')->andReturnUsing(function (FullTextSearchParams $params) use ($searcher) {
            $this->capturedParams = $params;

            return $searcher;
        });

        $this->app->instance(SearcherFactory::class, $searcherFactory);
    }

    private function seededVerse(): Verse
    {
        return Verse::where('trans', 1001)->where('usx_code', 'GEN')->firstOrFail();
    }

    public function test_search_returns_the_matching_verses(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse')
            ->assertOk()
            ->assertJsonPath('fullTextResult.hitCount', 1)
            ->assertJsonPath('fullTextResult.results.0.translation.abbrev', 'TESTTRANS');
    }

    public function test_search_without_hits_answers_with_an_empty_result(): void
    {
        $this->fakeFullTextSearch();

        $this->getJson('/api/search/nincsilyen')
            ->assertOk()
            ->assertJsonPath('fullTextResult.hitCount', 0)
            ->assertJsonPath('fullTextResult.results', []);
    }

    public function test_search_narrows_to_the_given_translation(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse/TESTTRANS2')->assertOk();

        $this->assertSame(1002, $this->capturedParams->translationId);
    }

    public function test_unknown_translation_is_rejected(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse/NINCSILYEN')->assertNotFound();
    }

    public function test_search_narrows_to_a_single_book_by_usx_code(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?book=GEN')->assertOk();

        $this->assertSame(['GEN'], array_keys($this->capturedParams->usxCodes));
    }

    public function test_search_narrows_to_a_single_book_by_hungarian_abbreviation(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?book=Jn')->assertOk();

        $this->assertSame(['JHN'], array_keys($this->capturedParams->usxCodes));
    }

    public function test_search_narrows_to_a_testament(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?book=new_testament')->assertOk();

        $usxCodes = array_keys($this->capturedParams->usxCodes);
        $this->assertContains('JHN', $usxCodes);
        $this->assertNotContains('GEN', $usxCodes);
    }

    public function test_whole_bible_is_searched_by_default(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse')->assertOk();

        $this->assertSame([], $this->capturedParams->usxCodes);
    }

    public function test_search_passes_the_limit_on(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?limit=5')->assertOk();

        $this->assertSame(5, $this->capturedParams->limit);
    }

    public function test_search_passes_the_grouping_on(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?grouping=chapter')->assertOk();

        $this->assertSame('chapter', $this->capturedParams->grouping);
    }

    public function test_unknown_book_is_rejected(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?book=nincsilyen')
            ->assertStatus(422)
            ->assertJsonValidationErrors('book');
    }

    public function test_limit_above_the_maximum_is_rejected(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?limit=100000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    public function test_unknown_grouping_is_rejected(): void
    {
        $this->fakeFullTextSearch([$this->seededVerse()]);

        $this->getJson('/api/search/verse?grouping=nincsilyen')
            ->assertStatus(422)
            ->assertJsonValidationErrors('grouping');
    }

    /**
     * A Greek verse with the Hungarian verse of the same place, which is what a Greek search
     * pairs up. Type 901 is the verse type the Greek text is aligned with.
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

    public function test_greek_search_finds_verses_by_their_strong_word(): void
    {
        $this->createGreekVerseWithTranslation(4, 1, 'ἡ ἀγάπη');

        $this->getJson('/api/greek-search/agape/TESTTRANS')
            ->assertOk()
            ->assertJsonPath('greekSearchResult.hitCount', 1)
            ->assertJsonPath('greekSearchResult.totalHitCount', 1)
            ->assertJsonPath('greekSearchResult.results.0.verses.0.greekText', 'ἡ ἀγάπη');
    }

    public function test_greek_search_finds_verses_by_strong_number(): void
    {
        $greekVerse = $this->createGreekVerseWithTranslation(4, 2, 'ἡ ἀγάπη μένει');

        $strongWord = new StrongWord();
        $strongWord->number = 26;
        $strongWord->lemma = 'ἀγάπη';
        $strongWord->transliteration = 'agapé';
        $strongWord->normalized = 'agape';
        $strongWord->save();
        $strongWord->greekVerses()->attach($greekVerse->id, ['position' => 0]);

        $this->getJson('/api/greek-search/26/TESTTRANS?mode=strong')
            ->assertOk()
            ->assertJsonPath('greekSearchResult.hitCount', 1)
            ->assertJsonPath('greekSearchResult.results.0.verses.0.greekText', 'ἡ ἀγάπη μένει');
    }

    public function test_greek_search_without_hits_answers_with_an_empty_result(): void
    {
        $this->createGreekVerseWithTranslation(4, 3, 'ἡ ἀγάπη');

        $this->getJson('/api/greek-search/nincsilyen/TESTTRANS')
            ->assertOk()
            ->assertJsonPath('greekSearchResult.hitCount', 0)
            ->assertJsonPath('greekSearchResult.results', []);
    }

    public function test_greek_search_limit_bounds_the_returned_verses(): void
    {
        $this->createGreekVerseWithTranslation(5, 1, 'ἀγάπη πρώτη');
        $this->createGreekVerseWithTranslation(5, 2, 'ἀγάπη δευτέρα');

        $this->getJson('/api/greek-search/agape/TESTTRANS?limit=1')
            ->assertOk()
            ->assertJsonPath('greekSearchResult.hitCount', 1)
            // The limit bounds the answer, but the caller still learns how many hits there are.
            ->assertJsonPath('greekSearchResult.totalHitCount', 2);
    }

    public function test_greek_search_narrows_to_the_given_book(): void
    {
        $this->createGreekVerseWithTranslation(6, 1, 'ἡ ἀγάπη');

        $this->getJson('/api/greek-search/agape/TESTTRANS?book=Jn')
            ->assertOk()
            ->assertJsonPath('greekSearchResult.hitCount', 0);
    }

    public function test_greek_search_rejects_an_unknown_mode(): void
    {
        $this->getJson('/api/greek-search/agape?mode=nincsilyen')
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');
    }
}
