<?php

namespace SzentirasHu\Data;

class UsxCodes
{
    private const OLD_TESTAMENT = [
        '1KI' => [
            'default' => [
                '1Kir',
            ],
        ],
        '1CH' => [
            'default' => [
                '1Krón',
            ],
        ],
        '1MA' => [
            'default' => [
                '1Mak',
                '1Makk',
            ],
        ],
        'GEN' => [
            'default' => [
                'Ter',
                '1Móz',
                '1Mozes',
                '1Mózes',
                '1Moz',
                'Teremtes',
                'Teremtés',
            ],
            'RUF' => [
                '1Móz',
            ],
        ],
        '1SA' => [
            'default' => [
                '1Sam',
                '1Sám',
                '1Samuel',
                '1Sámuel',
                '1Sámuél',
                'Samuel1',
                'Sámuel1',
                'SamuelI',
                'SámuelI',
            ],
        ],
        '2KI' => [
            'default' => [
                '2Kir',
            ],
        ],
        '2CH' => [
            'default' => [
                '2Krón',
            ],
        ],
        '2MA' => [
            'default' => [
                '2Mak',
                '2Makk',
            ],
        ],
        'EXO' => [
            'default' => [
                '2Moz',
                '2Móz',
                '2Mozes',
                '2Mózes',
                'Kiv',
                'Kivonulas',
                'Kivonulás',
            ],
        ],
        '2SA' => [
            'default' => [
                '2Sam',
                '2Sám',
                '2Samuel',
                '2Sámuel',
                '2Sámuél',
                'Samuel2',
                'Sámuel2',
                'SamuelII',
                'SámuelII',
            ],
        ],
        'LEV' => [
            'default' => [
                '3Moz',
                '3Móz',
                '3Mozes',
                '3Mózes',
                'Lev',
                'Leviták',
            ],
        ],
        'NUM' => [
            'default' => [
                '4Moz',
                '4Móz',
                '4Mozes',
                '4Mózes',
                'Szam',
                'Szám',
                'Szamok',
                'Számok',
            ],
        ],
        'DEU' => [
            'default' => [
                '5Moz',
                '5Móz',
                '5Mozes',
                '5Mózes',
                'Mtorv',
                'MTörv',
            ],
        ],
        'OBA' => [
            'default' => [
                'Abd',
            ],
        ],
        'HAG' => [
            'default' => [
                'Ag',
                'Agg',
                'Hag',
            ],
        ],
        'AMO' => [
            'default' => [
                'Ám',
                'Ámós',
            ],
        ],
        'BAR' => [
            'default' => [
                'Bár',
            ],
        ],
        'JDG' => [
            'default' => [
                'Bir',
                'Bír',
                'Birak',
                'Birák',
                'Bírák',
            ],
        ],
        'WIS' => [
            'default' => [
                'Bölcs',
            ],
        ],
        'DAN' => [
            'default' => [
                'Dán',
            ],
        ],
        'SNG' => [
            'default' => [
                'En',
                'Én',
                'Ének.Én',
                'Énekek',
                'ÉnekÉn',
            ],
        ],
        'ISA' => [
            'default' => [
                'Ésa',
                'Ézs',
                'Iz',
            ],
        ],
        'EST' => [
            'default' => [
                'Esz',
                'Eszt',
            ],
        ],
        'EZR' => [
            'default' => [
                'Ez',
                'Ezd',
                'Ezdr',
                'Ezsd',
                'Ezsdr',
            ],
        ],
        'EZK' => [
            'default' => [
                'Ezek',
                'Ezék',
                'Ezekias',
                'Ezekiás',
                'Ezékiás',
            ],
        ],
        'HAB' => [
            'default' => [
                'Hab',
            ],
        ],
        'HOS' => [
            'default' => [
                'Hós',
                'Oz',
                'Óz',
            ],
        ],
        'JER' => [
            'default' => [
                'Jer',
            ],
        ],
        'JOB' => [
            'default' => [
                'Jo',
                'Jób',
            ],
        ],
        'JOL' => [
            'default' => [
                'Joel',
                'Jóel',
            ],
        ],
        'JON' => [
            'default' => [
                'Jón',
            ],
        ],
        'JOS' => [
            'default' => [
                'Jozs',
                'Józs',
                'Jozsue',
                'Józsue',
                'Józsué',
            ],
        ],
        'LAM' => [
            'default' => [
                'Siralm',
                'Jsir',
                'Sír',
                'Siral',
            ],
            'RUF' => [
                'Sir'
            ]
        ],
        'SIR' => [
            'default' => [
                'Sirák',
                'Sirák fia',
                'Ecclesiasticus',
            ],
            'SZIT' => [
                'Sir'
            ],
            'RUF' => [
            ],
        ],
        'JDT' => [
            'default' => [
                'Judit',
            ],
            'SZIT' => [
                'Jud',
            ]
        ],
        'MAL' => [
            'default' => [
                'Mal',
                'Malak',
            ],
        ],
        'MIC' => [
            'default' => [
                'Mik',
            ],
        ],
        'NAM' => [
            'default' => [
                'Náh',
            ],
        ],
        'NEH' => [
            'default' => [
                'Neh',
            ],
        ],
        'PRO' => [
            'default' => [
                'Péld',
            ],
        ],
        'ECC' => [
            'default' => [
                'Préd',
            ],
        ],
        'RUT' => [
            'default' => [
                'Rut',
                'Rút',
                'Ruth',
                'Rúth',
            ],
        ],
        'ZEP' => [
            'default' => [
                'Sof',
                'Szof',
                'Zof',
            ],
        ],
        'TOB' => [
            'default' => [
                'Tób',
            ],
        ],
        'ZEC' => [
            'default' => [
                'Zak',
            ],
        ],
        'PSA' => [
            'default' => [
                'Zsolt',
                'Zsoltar',
                'Zsoltár',
                'Zsoltarok',
                'Zsoltárok',
            ],
        ],
    ];

    private const NEW_TESTAMENT = [
        '1JN' => [
            'default' => [
                '1Jan',
                '1Ján',
                '1Janos',
                '1János',
                '1Jn',
            ],
        ],
        '2JN' => [
            'default' => [
                '2Jan',
                '2Ján',
                '2Janos',
                '2János',
                '2Jn',
            ],
        ],
        '3JN' => [
            'default' => [
                '3Ján',
                '3Jn',
            ],
        ],
        'MAT' => [
            'default' => [
                'Mat',
                'Mát',
                'Mate',
                'Máté',
                'Mt',
            ],
        ],
        'MRK' => [
            'default' => [
                'Mar',
                'Már',
                'Mark',
                'Márk',
                'Mk',
            ],
        ],
        'LUK' => [
            'default' => [
                'Lk',
                'Luk',
                'Lukacs',
                'Lukács',
            ],
        ],
        'JHN' => [
            'default' => [
                'Jan',
                'Ján',
                'Janos',
                'János',
                'Jn',
            ],
        ],
        'ACT' => [
            'default' => [
                'ApCsel',
                'Csel',
            ],
        ],
        'ROM' => [
            'default' => [
                'Rom',
                'Róm',
            ],
        ],
        '1CO' => [
            'default' => [
                '1Kor',
            ],
        ],
        '2CO' => [
            'default' => [
                '2Kor',
            ],
        ],
        'GAL' => [
            'default' => [
                'Gal',
            ],
        ],
        'EPH' => [
            'default' => [
                'Ef',
                'Eféz',
            ],
        ],
        'PHP' => [
            'default' => [
                'Fil',
            ],
        ],
        'COL' => [
            'default' => [
                'Kol',
            ],
        ],
        '1TH' => [
            'default' => [
                '1Tessz',
                '1Tesz',
                '1Thess',
                '1Thessz',
            ],
        ],
        '2TH' => [
            'default' => [
                '2Tessz',
                '2Tesz',
                '2Thess',
                '2Thessz',
            ],
        ],
        '1TI' => [
            'default' => [
                '1Tim',
            ],
        ],
        '2TI' => [
            'default' => [
                '2Tim',
            ],
        ],
        'TIT' => [
            'default' => [
                'Tit',
                'Tít',
            ],
        ],
        'PHM' => [
            'default' => [
                'Filem',
            ],
        ],
        'HEB' => [
            'default' => [
                'Zs',
                'Zsid',
            ],
        ],
        'JAS' => [
            'default' => [
                'Jak',
            ],
        ],
        '1PE' => [
            'default' => [
                '1Pét',
                '1Pt',
            ],
        ],
        '2PE' => [
            'default' => [
                '2Pét',
                '2Pt',
            ],
        ],
        'JUD' => [
            'default' => [
                'Jud',
                'Júd',
                'Júdás',
            ],
        ],
        'REV' => [
            'default' => [
                'Jel',
            ],
        ],
    ];

    public static function oldTestamentUsx(): array
    {
        return array_keys(UsxCodes::OLD_TESTAMENT);
    }

    public static function newTestamentUsx(): array
    {
        return array_keys(UsxCodes::NEW_TESTAMENT);
    }

    public static function allUsx(): array
    {
        return array_merge(UsxCodes::oldTestamentUsx(), UsxCodes::newTestamentUsx());
    }

    public static function getUsxFromBookAbbrevAndTranslation(string $bookAbbrev, string $translation = "default"): ?string
    {
        static $abbrevToUsxPerTranslation = null;
        static $usxToTranslationToAbbrev = null;
        if ($abbrevToUsxPerTranslation === null) {
            $usxToTranslationToAbbrev = self::fullMapping();
            $abbrevToUsxPerTranslation = self::abbrevToUsxPerTranslation($usxToTranslationToAbbrev);
        }

        if (isset($abbrevToUsxPerTranslation[$translation][$bookAbbrev])) {
            return $abbrevToUsxPerTranslation[$translation][$bookAbbrev];
        }

        if (isset($abbrevToUsxPerTranslation["default"][$bookAbbrev])) {
            $specificUsx = $abbrevToUsxPerTranslation["default"][$bookAbbrev];

            if (
                isset($usxToTranslationToAbbrev[$specificUsx][$translation]) &&
                $usxToTranslationToAbbrev[$specificUsx][$translation] === []
            ) {
                return null; // no such book in this translation
            }

            return $specificUsx;
        }

        return null; // unknown book
    }

    public static function getPreferredAbbreviation(string $usxCode, string $translation): ?string
    {
        if (isset(UsxCodes::fullMapping()[$usxCode][$translation])) {
            $abbreviations = UsxCodes::fullMapping()[$usxCode][$translation];
        } else if (isset(UsxCodes::fullMapping()[$usxCode]["default"])) {
            $abbreviations = UsxCodes::fullMapping()[$usxCode]["default"];
        } else {
            $abbreviations = [];
        }

        if (count($abbreviations) > 0) {
            return $abbreviations[0];
        } else {
            return null;
        }
    }

    private static function fullMapping(): array
    {
        return array_merge(UsxCodes::OLD_TESTAMENT, UsxCodes::NEW_TESTAMENT);
    }

    private static function abbrevToUsxPerTranslation($inputMap): array
    {
        $outputMap = [];
        foreach ($inputMap as $usx => $translations) {
            foreach ($translations as $translationName => $abbrevs) {
                if (!isset($outputMap[$translationName])) {
                    $outputMap[$translationName] = [];
                }
                foreach ($abbrevs as $abbrev) {
                    $outputMap[$translationName][$abbrev] = $usx;
                }
            }
        }
        return $outputMap;
    }
}
