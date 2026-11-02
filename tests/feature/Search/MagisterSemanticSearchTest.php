<?php

namespace SzentirasHu\Test\Search;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The meaning search of /ai-search is answered by Magister: we send the question, Magister
 * names the passages (in SZIT numbering) with the Church documents citing them, and we render
 * them in our translations.
 */
class MagisterSemanticSearchTest extends FastDatabaseTestCase
{
    private const MAGISTER_URL = 'https://magister.test';

    private const SZIT_ID = 1003;

    private const TESTTRANS_ID = 1001;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.magister.url' => self::MAGISTER_URL,
            'services.magister.reference_translation' => 'SZIT',
            'settings.defaultTranslationAbbrev' => 'SZIT',
            'settings.enabledTranslations' => [self::SZIT_ID, self::TESTTRANS_ID],
        ]);
        Cache::flush();

        AnonymousId::query()->create(['token' => 'magister-test-token']);

        config([
            'translations.ids.' . self::SZIT_ID => 'SZIT',
            'translations.definitions.SZIT.id' => self::SZIT_ID,
        ]);
        $szit = new Translation();
        $szit->id = self::SZIT_ID;
        $szit->name = 'Szent István Társulat';
        $szit->abbrev = 'SZIT';
        $szit->denom = 'katolikus';
        $szit->lang = 'hu';
        $szit->save();

        $this->addBookWithVerses(1145, $szit, 'Róm', 'ROM', 901, [12, 13, 14]);
        $this->addBookWithVerses(1101, $szit, 'Ter', 'GEN', 901, [1]);
        $this->addBookWithVerses(99145, Translation::find(self::TESTTRANS_ID), 'Róm', 'ROM', 6, [12, 13, 14]);
    }

    /**
     * @param  array<int, int>  $verseNumbers  verses of chapter 5 (Róm) or 1 (Ter)
     */
    private function addBookWithVerses(int $bookId, Translation $translation, string $abbrev, string $usxCode, int $verseType, array $verseNumbers): void
    {
        $book = new Book();
        $book->id = $bookId;
        $book->order = $usxCode === 'GEN' ? 101 : 245;
        $book->abbrev = $abbrev;
        $book->name = "{$abbrev}.";
        $book->link = $abbrev;
        $book->old_testament = $usxCode === 'GEN' ? 1 : 0;
        $book->usx_code = $usxCode;
        $book->translation()->associate($translation);
        $book->save();

        $chapter = $usxCode === 'GEN' ? 1 : 5;
        foreach ($verseNumbers as $verseNumber) {
            $verse = new Verse();
            $verse->trans = $translation->id;
            $verse->gepi = sprintf('%d%03d%03d00', $book->order, $chapter, $verseNumber);
            $verse->usx_code = $usxCode;
            $verse->book_id = $bookId;
            $verse->chapter = $chapter;
            $verse->numv = $verseNumber;
            $verse->tip = $verseType;
            $verse->verse = "{$translation->abbrev} {$usxCode} {$chapter},{$verseNumber} szövege";
            $verse->verseroot = "verseroot {$verse->gepi}";
            $verse->ido = '2026-11-02';
            $verse->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $passages
     */
    private function fakeMagister(array $passages): void
    {
        Http::fake([
            self::MAGISTER_URL . '/api/szentiras_kereses*' => Http::response([
                'question' => 'áteredő bűn',
                'translations' => ['SZIT'],
                'passages' => $passages,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function romansPassage(): array
    {
        return [
            'ref' => 'Róm 5,12-14',
            'heading' => 'A bűn és a kegyelem szembeállítása.',
            'score' => 0.452,
            'cited_by' => ['KEK 388', 'KEK 602'],
            'match' => ['scope' => 'verse', 'translation' => 'SZIT', 'ref' => 'Róm 5,13'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genesisPassage(): array
    {
        return ['ref' => 'Ter 1,1', 'heading' => 'A teremtés', 'score' => 0.4, 'cited_by' => [], 'match' => null];
    }

    /**
     * A logged-in visitor, so neither the captcha nor the per-session limit gets in the way.
     *
     * @param  array<string, string>  $parameters
     */
    private function search(array $parameters = []): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['anonymous_token' => 'magister-test-token'])
            ->post('/ai-search/search', array_merge(['textToSearchAi' => 'áteredő bűn'], $parameters));
    }

    public function test_question_is_sent_to_magister(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $this->search()->assertOk();

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), self::MAGISTER_URL . '/api/szentiras_kereses')
            && $request['q'] === 'áteredő bűn');
    }

    public function test_passages_are_rendered_in_every_translation_with_the_citing_documents(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $response = $this->search();

        $response->assertOk();
        $response->assertSee('A bűn és a kegyelem szembeállítása.');
        $response->assertSee('SZIT ROM 5,12 szövege');
        $response->assertSee('TESTTRANS ROM 5,14 szövege');
        $response->assertSee('href="/SZIT/Róm 5,12-14"', false);
        $response->assertSee('href="/TESTTRANS/Róm 5,12-14"', false);
        $response->assertSee('Az Egyház tanításában:');
        $response->assertSee('href="' . self::MAGISTER_URL . '/?q=KEK%20388"', false);
        $response->assertSee('KEK 602');
    }

    public function test_best_matching_verse_is_highlighted(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $hits = $this->search()->viewData('hits');

        $this->assertSame(['24500501300'], $hits[0]->translations[0]['highlightedGepis']);
    }

    public function test_page_links_the_question_to_magister(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $this->search()->assertSee('href="' . self::MAGISTER_URL . '/?q=%C3%A1tered%C5%91%20b%C5%B1n"', false);
    }

    public function test_book_filter_drops_passages_of_other_books(): void
    {
        $this->fakeMagister([$this->genesisPassage(), $this->romansPassage()]);

        $response = $this->search(['usxCode' => 'ROM']);

        $response->assertSee('SZIT ROM 5,12 szövege');
        $response->assertDontSee('SZIT GEN 1,1 szövege');
    }

    public function test_translation_filter_shows_only_that_translation(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $response = $this->search(['translationAbbrev' => 'TESTTRANS']);

        $response->assertSee('TESTTRANS ROM 5,12 szövege');
        $response->assertDontSee('SZIT ROM 5,12 szövege');
    }

    public function test_passage_missing_from_a_translation_is_shown_in_the_others(): void
    {
        $this->fakeMagister([$this->genesisPassage()]);

        $hits = $this->search()->viewData('hits');

        $this->assertCount(1, $hits);
        $this->assertSame(['SZIT'], array_map(fn (array $rendered): string => $rendered['translation']->abbrev, $hits[0]->translations));
    }

    public function test_unparseable_and_unknown_references_are_skipped(): void
    {
        $this->fakeMagister([
            ['ref' => '???', 'heading' => null, 'score' => 0.5, 'cited_by' => [], 'match' => null],
            ['ref' => 'Bár 3,9', 'heading' => null, 'score' => 0.5, 'cited_by' => [], 'match' => null],
            $this->romansPassage(),
        ]);

        $hits = $this->search()->viewData('hits');

        $this->assertCount(1, $hits);
        $this->assertSame('Róm 5,12-14', $hits[0]->passage->reference);
    }

    public function test_no_passages_says_so(): void
    {
        $this->fakeMagister([]);

        $this->search()->assertOk()->assertSee('Nem találtunk a kérdéshez illő szentírási szakaszt.');
    }

    public function test_magister_failure_shows_a_notice_instead_of_an_error(): void
    {
        Http::fake([self::MAGISTER_URL . '/*' => Http::response('Bad Gateway', 502)]);

        $response = $this->search();

        $response->assertOk();
        $response->assertSee('A jelentés szerinti kereső most nem érhető el.');
    }

    public function test_magister_answer_without_passages_counts_as_failure(): void
    {
        Http::fake([self::MAGISTER_URL . '/*' => Http::response(['detail' => 'no Bible index'])]);

        $this->search()->assertOk()->assertSee('A jelentés szerinti kereső most nem érhető el.');
    }

    public function test_same_question_is_answered_from_cache(): void
    {
        $this->fakeMagister([$this->romansPassage()]);

        $this->search()->assertOk();
        $this->search()->assertOk();

        Http::assertSentCount(1);
    }

    public function test_failures_are_not_cached(): void
    {
        Http::fakeSequence(self::MAGISTER_URL . '/*')
            ->push('Bad Gateway', 502)
            ->push(['passages' => [$this->romansPassage()]]);

        $this->search()->assertSee('A jelentés szerinti kereső most nem érhető el.');
        $this->search()->assertSee('SZIT ROM 5,12 szövege');
    }

    public function test_typed_reference_goes_straight_to_the_passage(): void
    {
        Http::fake();

        $this->search(['textToSearchAi' => 'Római 5:12'])->assertRedirect('/SZIT/Róm5,12');

        Http::assertNothingSent();
    }

    public function test_typed_reference_needs_no_captcha(): void
    {
        Http::fake();

        $this->post('/ai-search/search', ['textToSearchAi' => 'Rómaiakhoz írt levél 5'])->assertRedirect('/SZIT/Róm5');
    }

    public function test_typed_reference_opens_in_the_chosen_translation(): void
    {
        $this->search(['textToSearchAi' => 'Római 5,12', 'translationAbbrev' => 'TESTTRANS'])
            ->assertRedirect('/TESTTRANS/Róm5,12');
    }

    public function test_typed_reference_falls_back_to_the_default_translation_without_the_book(): void
    {
        $this->addBookWithVerses(1127, Translation::find(self::SZIT_ID), 'Dán', 'DAN', 901, [1]);

        $this->search(['textToSearchAi' => 'Dániel könyve', 'translationAbbrev' => 'TESTTRANS'])
            ->assertRedirect('/SZIT/Dán');
    }

    public function test_link_from_the_normal_search_redirects_a_reference(): void
    {
        $this->get('/ai-search?textToSearchAi=' . rawurlencode('Római 5,12'))->assertRedirect('/SZIT/Róm5,12');
    }

    public function test_link_from_the_normal_search_prefills_a_question(): void
    {
        $this->get('/ai-search?textToSearchAi=' . rawurlencode('áteredő bűn'))
            ->assertOk()
            ->assertSee('value="áteredő bűn"', false);
    }
}
