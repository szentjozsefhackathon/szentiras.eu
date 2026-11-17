<?php

namespace SzentirasHu\Data;

/**
 * The names people search the books by ("Máté evangéliuma", "Jelenések könyve"), independent
 * of the translation. A translation's own book name ("Az evangélium Máté szerint") stays the
 * page heading; these feed the page title, the "Más néven" line and the structured data.
 * The first name of each book is the primary one.
 */
class BookCommonNames
{
    /**
     * @var array<string, list<string>>
     */
    public const NAMES = [
        'GEN' => ['Teremtés könyve', 'Mózes első könyve', 'Genezis'],
        'EXO' => ['Kivonulás könyve', 'Mózes második könyve', 'Exodus'],
        'LEV' => ['Leviták könyve', 'Mózes harmadik könyve', 'Leviticus'],
        'NUM' => ['Számok könyve', 'Mózes negyedik könyve', 'Numeri'],
        'DEU' => ['Második Törvénykönyv', 'Mózes ötödik könyve', 'Deuteronomium'],
        'JOS' => ['Józsue könyve'],
        'JDG' => ['Bírák könyve'],
        'RUT' => ['Rut könyve', 'Ruth könyve'],
        '1SA' => ['Sámuel első könyve'],
        '2SA' => ['Sámuel második könyve'],
        '1KI' => ['Királyok első könyve'],
        '2KI' => ['Királyok második könyve'],
        '1CH' => ['Krónikák első könyve'],
        '2CH' => ['Krónikák második könyve'],
        'EZR' => ['Ezdrás könyve', 'Ezsdrás könyve'],
        'NEH' => ['Nehemiás könyve'],
        'TOB' => ['Tóbiás könyve', 'Tóbit könyve'],
        'JDT' => ['Judit könyve'],
        'EST' => ['Eszter könyve'],
        '1MA' => ['Makkabeusok első könyve'],
        '2MA' => ['Makkabeusok második könyve'],
        'JOB' => ['Jób könyve'],
        'PSA' => ['Zsoltárok könyve', 'Zsoltárok'],
        'PRO' => ['Példabeszédek könyve', 'Példabeszédek'],
        'ECC' => ['Prédikátor könyve', 'Kohelet'],
        'SNG' => ['Énekek éneke'],
        'WIS' => ['Bölcsesség könyve'],
        'SIR' => ['Sirák fia könyve', 'Sirák fia'],
        'ISA' => ['Izajás könyve', 'Ézsaiás könyve'],
        'JER' => ['Jeremiás könyve'],
        'LAM' => ['Jeremiás siralmai', 'Siralmak könyve'],
        'BAR' => ['Báruk könyve'],
        'EZK' => ['Ezékiel könyve', 'Ezekiel könyve'],
        'DAN' => ['Dániel könyve'],
        'HOS' => ['Ozeás könyve', 'Hóseás könyve'],
        'JOL' => ['Joel könyve', 'Jóel könyve'],
        'AMO' => ['Ámosz könyve', 'Ámós könyve'],
        'OBA' => ['Abdiás könyve'],
        'JON' => ['Jónás könyve'],
        'MIC' => ['Mikeás könyve'],
        'NAM' => ['Náhum könyve'],
        'HAB' => ['Habakuk könyve'],
        'ZEP' => ['Szofoniás könyve', 'Sofóniás könyve'],
        'HAG' => ['Aggeus könyve', 'Haggeus könyve'],
        'ZEC' => ['Zakariás könyve'],
        'MAL' => ['Malakiás könyve'],
        'MAT' => ['Máté evangéliuma'],
        'MRK' => ['Márk evangéliuma'],
        'LUK' => ['Lukács evangéliuma'],
        'JHN' => ['János evangéliuma'],
        'ACT' => ['Apostolok cselekedetei'],
        'ROM' => ['Római levél', 'Rómaiakhoz írt levél'],
        '1CO' => ['Első korintusi levél', 'Korintusiakhoz írt első levél', '1 Korintus'],
        '2CO' => ['Második korintusi levél', 'Korintusiakhoz írt második levél', '2 Korintus'],
        'GAL' => ['Galata levél', 'Galatákhoz írt levél'],
        'EPH' => ['Efezusi levél', 'Efezusiakhoz írt levél'],
        'PHP' => ['Filippi levél', 'Filippiekhez írt levél'],
        'COL' => ['Kolosszei levél', 'Kolosszeiekhez írt levél'],
        '1TH' => ['Első tesszalonikai levél', 'Tesszalonikaiakhoz írt első levél'],
        '2TH' => ['Második tesszalonikai levél', 'Tesszalonikaiakhoz írt második levél'],
        '1TI' => ['Első Timóteus-levél', 'Timóteushoz írt első levél', '1 Timóteus'],
        '2TI' => ['Második Timóteus-levél', 'Timóteushoz írt második levél', '2 Timóteus'],
        'TIT' => ['Titusz-levél', 'Tituszhoz írt levél'],
        'PHM' => ['Filemon-levél', 'Filemonhoz írt levél'],
        'HEB' => ['Zsidókhoz írt levél', 'Zsidó levél'],
        'JAS' => ['Jakab levele'],
        '1PE' => ['Péter első levele'],
        '2PE' => ['Péter második levele'],
        '1JN' => ['János első levele'],
        '2JN' => ['János második levele'],
        '3JN' => ['János harmadik levele'],
        'JUD' => ['Júdás levele'],
        'REV' => ['Jelenések könyve', 'Apokalipszis'],
    ];

    public static function primary(string $usxCode): ?string
    {
        return self::NAMES[$usxCode][0] ?? null;
    }

    /**
     * The name a page title leads with instead of the translation's own book name, or null to
     * keep the own name. Protestant translations already name their books the way their readers
     * search ("Mózes első könyve"), so they keep theirs.
     */
    public static function titleNameFor(string $usxCode, string $officialName, bool $isProtestantTranslation): ?string
    {
        $primary = self::primary($usxCode);
        if ($isProtestantTranslation || $primary === null || self::comparisonKey($primary) === self::comparisonKey($officialName)) {
            return null;
        }

        return $primary;
    }

    /**
     * "A jelenések könyve" → "Jelenések könyve", for names that do not start a sentence.
     */
    public static function withoutArticle(string $name): string
    {
        $withoutArticle = preg_replace('/^(a|az)\s+/iu', '', trim($name));

        return mb_strtoupper(mb_substr($withoutArticle, 0, 1)).mb_substr($withoutArticle, 1);
    }

    /**
     * The popular names of the book that differ from the given official name.
     *
     * @return list<string>
     */
    public static function alternativesTo(string $usxCode, string $officialName): array
    {
        $officialKey = self::comparisonKey($officialName);

        return array_values(array_filter(
            self::NAMES[$usxCode] ?? [],
            fn (string $name): bool => self::comparisonKey($name) !== $officialKey,
        ));
    }

    /**
     * Case and leading article do not make a name different: "A jelenések könyve" = "Jelenések könyve".
     */
    private static function comparisonKey(string $name): string
    {
        return preg_replace('/^(a|az)\s+/u', '', mb_strtolower(trim($name), 'UTF-8'));
    }
}
