<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class TextServiceTest extends FastDatabaseTestCase
{
    public function testSameChapterRangeText() {

        /** @var \SzentirasHu\Service\Text\TextService $service */
        $service = \App::make(\SzentirasHu\Service\Text\TextService::class);
        /** @var \SzentirasHu\Service\Text\TranslationService $translationService*/
        $translationService = \App::make(\SzentirasHu\Service\Text\TranslationService::class);
        $defaultTranslation = $translationService->getDefaultTranslation();
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-4'), $defaultTranslation);
        $this->assertEquals("verse 100100200300 verse 100100200400", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300", $text);
    }

    public function testTranslatedVersesCachedWithoutExpiry()
    {
        /** @var \SzentirasHu\Service\Text\TextService $service */
        $service = \App::make(\SzentirasHu\Service\Text\TextService::class);
        /** @var \SzentirasHu\Service\Text\TranslationService $translationService */
        $translationService = \App::make(\SzentirasHu\Service\Text\TranslationService::class);
        $defaultTranslation = $translationService->getDefaultTranslation();
        $reference = CanonicalReference::fromString('Ter 2,3');

        $service->getTranslatedVerses($reference, $defaultTranslation);

        $cacheKey = $service->getTranslatedVersesCacheKey($reference, $defaultTranslation);
        $this->assertTrue(Cache::has($cacheKey));

        // Verse text only changes on deploys or source updates that flush the
        // cache, so the entry must outlive the former one-hour TTL.
        $this->travel(2)->hours();
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function testInvalidatingTranslationTextsRefreshesOnlyThatTranslation()
    {
        /** @var \SzentirasHu\Service\Text\TextService $service */
        $service = \App::make(\SzentirasHu\Service\Text\TextService::class);
        /** @var \SzentirasHu\Service\Text\TranslationService $translationService */
        $translationService = \App::make(\SzentirasHu\Service\Text\TranslationService::class);
        $defaultTranslation = $translationService->getDefaultTranslation();
        $reference = CanonicalReference::fromString('Ter 2,3');
        Cache::forever('unrelated_entry', 'kept');
        Cache::forever("bookHeaders_{$defaultTranslation->abbrev}", ['stale']);

        $this->assertEquals('verse 100100200300', $service->getPureText($reference, $defaultTranslation));
        \SzentirasHu\Data\Entity\Verse::where('trans', $defaultTranslation->id)->where('gepi', '100100200300')->update(['verse' => 'javított vers']);
        $this->assertEquals('verse 100100200300', $service->getPureText($reference, $defaultTranslation));

        $service->invalidateTranslationTexts($defaultTranslation);

        $this->assertEquals('javított vers', $service->getPureText($reference, $defaultTranslation));
        $this->assertEquals('kept', Cache::get('unrelated_entry'));
        $this->assertFalse(Cache::has("bookHeaders_{$defaultTranslation->abbrev}"));
    }
}
