<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Config;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * KAL numbers the Psalms the Vulgate way: its Psalm 22 is Psalm 23 in the other translations.
 */
class VulgataNumberingTest extends FastDatabaseTestCase
{
    private function toVulgata(string $reference): string
    {
        return app(NumberingSchemeService::class)->toVulgata(CanonicalReference::fromString($reference))->toString();
    }

    public function test_whole_chapter_converts_to_the_vulgate_verses(): void
    {
        $this->assertSame('Zsolt 22,1-6', $this->toVulgata('Zsolt 23'));
        $this->assertSame('Zsolt 90,1-16', $this->toVulgata('Zsolt 91'));
    }

    public function test_verses_convert_one_by_one(): void
    {
        $this->assertSame('Zsolt 22,1', $this->toVulgata('Zsolt 23,1'));
    }

    public function test_unmapped_references_keep_their_numbers(): void
    {
        $this->assertSame('Zsolt 1', $this->toVulgata('Zsolt 1'));
        $this->assertSame('Mt 5,1-12', $this->toVulgata('Mt 5,1-12'));
    }

    public function test_vulgate_chapter_maps_to_the_default_chapter(): void
    {
        $numberingSchemeService = app(NumberingSchemeService::class);

        $this->assertSame(23, $numberingSchemeService->defaultChapterOf('PSA', 22));
        $this->assertSame(1, $numberingSchemeService->defaultChapterOf('PSA', 1));
    }

    public function test_only_translations_marked_vulgata_use_it(): void
    {
        $numberingSchemeService = app(NumberingSchemeService::class);

        $this->assertTrue($numberingSchemeService->usesVulgataNumbering('KAL'));
        $this->assertFalse($numberingSchemeService->usesVulgataNumbering('SZIT'));
    }

    public function test_chapter_banner_of_a_vulgate_numbered_translation_uses_the_default_chapter(): void
    {
        Config::set('translations.definitions.TESTTRANS.numberingScheme', 'vulgata');
        Book::query()->whereKey(99101)->update(['usx_code' => 'PSA', 'abbrev' => 'Zsolt', 'name' => 'A Zsoltárok könyve']);
        Verse::query()->where('book_id', 99101)->update(['usx_code' => 'PSA']);
        FamousPassage::factory()->create([
            'name' => '51. zsoltár',
            'alternative_names' => ['Miserere'],
            'reference' => 'Zsolt 51',
            'usx_code' => 'PSA',
            'chapter_from' => 51,
            'chapter_to' => 51,
        ]);

        $this->get('/TESTTRANS/Zsolt50')
            ->assertOk()
            ->assertSee('51. zsoltár – Miserere (Zsolt 51): magyarázat és fordítások');
    }
}
