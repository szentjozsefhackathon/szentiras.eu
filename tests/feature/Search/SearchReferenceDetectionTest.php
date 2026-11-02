<?php

namespace SzentirasHu\Test\Search;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Service\Search\SearchService;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The normal search shows the passage when the text is a reference, also when it is typed the
 * way people type it ("Efezus 2,8") rather than with an abbreviation the parser knows.
 */
class SearchReferenceDetectionTest extends FastDatabaseTestCase
{
    private const SZIT_ID = 1003;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'settings.defaultTranslationAbbrev' => 'SZIT',
            'settings.enabledTranslations' => [self::SZIT_ID],
            'translations.ids.' . self::SZIT_ID => 'SZIT',
            'translations.definitions.SZIT.id' => self::SZIT_ID,
        ]);
        Cache::flush();

        $szit = new Translation();
        $szit->id = self::SZIT_ID;
        $szit->name = 'Szent István Társulat';
        $szit->abbrev = 'SZIT';
        $szit->denom = 'katolikus';
        $szit->lang = 'hu';
        $szit->save();

        $book = new Book();
        $book->id = 1149;
        $book->order = 249;
        $book->abbrev = 'Ef';
        $book->name = 'Efezusiaknak írt levél';
        $book->link = 'Ef';
        $book->old_testament = 0;
        $book->usx_code = 'EPH';
        $book->translation()->associate($szit);
        $book->save();

        foreach ([8, 9] as $verseNumber) {
            $verse = new Verse();
            $verse->trans = self::SZIT_ID;
            $verse->gepi = sprintf('249002%03d00', $verseNumber);
            $verse->usx_code = 'EPH';
            $verse->book_id = $book->id;
            $verse->chapter = 2;
            $verse->numv = $verseNumber;
            $verse->tip = 901;
            $verse->verse = "Kegyelemből üdvözültetek, Ef 2,{$verseNumber}";
            $verse->verseroot = "kegyelem {$verse->gepi}";
            $verse->ido = '2026-11-02';
            $verse->save();
        }
    }

    public function test_reference_with_known_abbreviation_is_kept(): void
    {
        $this->assertSame('Ef 2,8', app(SearchService::class)->resolveReferenceText('Ef 2,8'));
    }

    public function test_typed_reference_is_rewritten_with_the_default_abbreviation(): void
    {
        $searchService = app(SearchService::class);

        $this->assertSame('Ef 2,8', $searchService->resolveReferenceText('Efezus 2,8'));
        $this->assertSame('Ef 2,8-9', $searchService->resolveReferenceText('efezusiakhoz 2:8-9'));
        $this->assertSame('Ef', $searchService->resolveReferenceText('Efezusi levél'));
    }

    public function test_text_that_is_not_a_reference_is_not_one(): void
    {
        $searchService = app(SearchService::class);

        $this->assertNull($searchService->resolveReferenceText('kegyelemből üdvözültetek'));
        $this->assertNull($searchService->resolveReferenceText(''));
        $this->assertNull($searchService->resolveReferenceText(null));
    }

    public function test_book_missing_from_the_default_translation_is_not_a_reference(): void
    {
        $this->assertNull(app(SearchService::class)->resolveReferenceText('Jelenések 12,9'));
    }

    public function test_search_page_shows_the_typed_reference(): void
    {
        $this->post('/kereses/search', ['textToSearch' => 'Efezus 2,8'])
            ->assertOk()
            ->assertSee('Kegyelemből üdvözültetek, Ef 2,8')
            ->assertSee('href="/SZIT/Ef 2,8"', false);
    }
}
