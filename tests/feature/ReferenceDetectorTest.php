<?php

namespace SzentirasHu\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use SzentirasHu\Service\Reference\ReferenceDetector;
use SzentirasHu\Test\Common\TestCase;

/**
 * Cases are real /ai-search queries from the production logs that the normal search did not find.
 */
class ReferenceDetectorTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, ?string}>
     */
    public static function cases(): array
    {
        return [
            'full name with colon' => ['Filippi 1:23', 'PHP', '1,23'],
            'jelenesek' => ['Jelenések 12:9', 'REV', '12,9'],
            'efezus verse' => ['Efezus 2,8', 'EPH', '2,8'],
            'efezus chapter' => ['Efezus 6', 'EPH', '6'],
            'jeremias' => ['Jeremiás 29', 'JER', '29'],
            'number glued' => ['1Korintus2', '1CO', '2'],
            'number spaced' => ['1 Korintus 15', '1CO', '15'],
            'trailing dot' => ['Józsue 6.', 'JOS', '6'],
            'dotted verse' => ['Józsue 6.1.', 'JOS', '6,1'],
            'psalm ordinal' => ['103. Zsoltár', 'PSA', '103'],
            'psalm ordinal glued' => ['91.zsoltár', 'PSA', '91'],
            'lowercase' => ['eszter 2,3', 'EST', '2,3'],
            'book word' => ['Júdás levele', 'JUD', ''],
            'konyve with stray accent' => ['Dàniel könyve', 'DAN', ''],
            'roman numeral and suffix' => ['I. Timóteushoz', '1TI', ''],
            'number word' => ['Első korintusi levél 13', '1CO', '13'],
            'written to' => ['Rómaiakhoz írt levél 5', 'ROM', '5'],
            'typo' => ['Ronai level', 'ROM', ''],
            'abbreviation' => ['Ef 2,8', 'EPH', '2,8'],
            'abbreviation range' => ['Mt 5,3-12', 'MAT', '5,3-12'],
            'bare person name' => ['Samuel', null, null],
            'not a book' => ['Jeromos', null, null],
            'quotation' => ['az úr nékem pásztorom', null, null],
            'ambiguous without number' => ['Korintus 13', null, null],
            'stray numbers' => ['Sir 19,4 12', null, null],
            'question' => ['áteredő bűn', null, null],
        ];
    }

    #[DataProvider('cases')]
    public function test_detects_typed_references(string $text, ?string $usxCode, ?string $chapterVerses): void
    {
        $detected = (new ReferenceDetector())->detect($text);

        $this->assertSame($usxCode, $detected?->usxCode, $text);
        $this->assertSame($chapterVerses, $detected?->chapterVerses, $text);
    }
}
