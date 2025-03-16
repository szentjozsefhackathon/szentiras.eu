<?php

namespace SzentirasHu\Test;

use Artisan;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\TestCase;

class TextServiceTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        /* Clean up caches, to not be affected by runtime */

        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        \Config::set(
            'translations',
            array_merge_recursive(
                \Config::get('translations'),
                [
                    'TESTTRANS' => [
                        'verseTypes' =>
                        [
                            'text' => [6, 901],
                            'heading' => [0 => 5, 1 => 10, 2 => 20, 3 => 30],
                            'footnote' => [120, 2001, 2002],
                            'poemLine' => [902],
                            'xref' => [920]
                        ],
                        'textSource' => env('TEXT_SOURCE_KNB'),
                        'id' => 1001
                    ]
                ]
            )
        );
    }


    public function testSameChapterRangeText() {
        
        /** @var \SzentirasHu\Service\Text\TextService $service */
        $service = \App::make(\SzentirasHu\Service\Text\TextService::class);
        /** @var \SzentirasHu\Service\Text\TranslationService $translationService*/
        $translationService = \App::make(\SzentirasHu\Service\Text\TranslationService::class);
        $defaultTranslation = $translationService->getDefaultTranslation();
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300 ", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-4'), $defaultTranslation);
        $this->assertEquals("verse 100100200300 verse 100100200400 ", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300 ", $text);
    }
}
