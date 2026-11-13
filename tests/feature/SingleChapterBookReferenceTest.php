<?php

namespace SzentirasHu\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\TestCase;

class SingleChapterBookReferenceTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function referencesWithoutTranslation(): array
    {
        return [
            'verse range without chapter' => ['2Jn4-9', '2Jn 1,4-9'],
            'single verse without chapter' => ['2Jn 4', '2Jn 1,4'],
            'multiple verse ranges' => ['3Jn 2.5-7', '3Jn 1,2.5-7'],
            'verse part' => ['Júd 3a-5', 'Júd 1,3a-5'],
            'Philemon' => ['Filem 1-25', 'Filem 1,1-25'],
            'Obadiah' => ['Abd 15', 'Abd 1,15'],
            'whole book as chapter one' => ['2Jn 1', '2Jn 1'],
            'explicit chapter and verses' => ['2Jn 1,4-9', '2Jn 1,4-9'],
            'whole book' => ['2Jn', '2Jn'],
            'multi-chapter book untouched' => ['Jn 4-9', 'Jn 4-9'],
            'only the single-chapter book converted' => ['2Jn 4-9; Jn 3', '2Jn 1,4-9; Jn 3'],
            'abbreviation meaning Judit in some translation' => ['Jud 4-9', 'Jud 4-9'],
        ];
    }

    #[DataProvider('referencesWithoutTranslation')]
    public function testReferenceWithoutTranslation(string $input, string $expected): void
    {
        $this->assertSame($expected, CanonicalReference::fromString($input)->toString());
    }

    public function testTranslationDecidesAmbiguousAbbreviation(): void
    {
        $szitId = 1;
        $knbId = 3;

        $this->assertSame('Jud 4-9', CanonicalReference::fromString('Jud 4-9', $szitId)->toString());
        $this->assertSame('Júd 1,4-9', CanonicalReference::fromString('Júd 4-9', $szitId)->toString());
        $this->assertSame('Jud 1,4-9', CanonicalReference::fromString('Jud 4-9', $knbId)->toString());
    }
}
