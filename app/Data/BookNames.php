<?php

namespace SzentirasHu\Data;

/**
 * Hungarian book names as people type them ("Efezus", "Jelenések", "Timóteus"), lowercase and
 * without accents. Each name maps a book number to a USX code; '' is the name without a number,
 * so "korintus" alone is ambiguous and only "1 korintus" / "2 korintus" resolve.
 */
class BookNames
{
    public const NAMES = [
        'teremtes' => ['' => 'GEN'], 'mozes' => [1 => 'GEN', 2 => 'EXO', 3 => 'LEV', 4 => 'NUM', 5 => 'DEU'],
        'kivonulas' => ['' => 'EXO'], 'levitak' => ['' => 'LEV'], 'levitikus' => ['' => 'LEV'], 'szamok' => ['' => 'NUM'],
        'masodik torvenykonyv' => ['' => 'DEU'], 'torvenykonyv' => ['' => 'DEU'],
        'jozsue' => ['' => 'JOS'], 'birak' => ['' => 'JDG'], 'rut' => ['' => 'RUT'], 'ruth' => ['' => 'RUT'],
        'samuel' => [1 => '1SA', 2 => '2SA'], 'kiralyok' => [1 => '1KI', 2 => '2KI'], 'kronikak' => [1 => '1CH', 2 => '2CH'],
        'ezdras' => ['' => 'EZR'], 'nehemias' => ['' => 'NEH'], 'tobias' => ['' => 'TOB'], 'tobit' => ['' => 'TOB'],
        'judit' => ['' => 'JDT'], 'eszter' => ['' => 'EST'], 'makkabeusok' => [1 => '1MA', 2 => '2MA'],
        'job' => ['' => 'JOB'], 'zsoltar' => ['' => 'PSA'], 'zsoltarok' => ['' => 'PSA'],
        'peldabeszedek' => ['' => 'PRO'], 'predikator' => ['' => 'ECC'], 'kohelet' => ['' => 'ECC'],
        'enekek eneke' => ['' => 'SNG'], 'bolcsesseg' => ['' => 'WIS'], 'sirak' => ['' => 'SIR'], 'sirak fia' => ['' => 'SIR'],
        'izajas' => ['' => 'ISA'], 'izaias' => ['' => 'ISA'], 'ezsaias' => ['' => 'ISA'], 'jeremias' => ['' => 'JER'],
        'siralmak' => ['' => 'LAM'], 'baruk' => ['' => 'BAR'], 'ezekiel' => ['' => 'EZK'], 'daniel' => ['' => 'DAN'],
        'ozeas' => ['' => 'HOS'], 'hoseas' => ['' => 'HOS'], 'joel' => ['' => 'JOL'], 'amosz' => ['' => 'AMO'], 'amos' => ['' => 'AMO'],
        'abdias' => ['' => 'OBA'], 'jonas' => ['' => 'JON'], 'mikeas' => ['' => 'MIC'], 'nahum' => ['' => 'NAM'],
        'habakuk' => ['' => 'HAB'], 'szofonias' => ['' => 'ZEP'], 'sofonias' => ['' => 'ZEP'], 'aggeus' => ['' => 'HAG'],
        'haggeus' => ['' => 'HAG'], 'zakarias' => ['' => 'ZEC'], 'malakias' => ['' => 'MAL'],
        'mate' => ['' => 'MAT'], 'mark' => ['' => 'MRK'], 'lukacs' => ['' => 'LUK'],
        'janos' => ['' => 'JHN', 1 => '1JN', 2 => '2JN', 3 => '3JN'],
        'apostolok cselekedetei' => ['' => 'ACT'], 'cselekedetek' => ['' => 'ACT'],
        'romai' => ['' => 'ROM'], 'romaiak' => ['' => 'ROM'],
        'korintus' => [1 => '1CO', 2 => '2CO'], 'korinthus' => [1 => '1CO', 2 => '2CO'], 'korintusiak' => [1 => '1CO', 2 => '2CO'],
        'galata' => ['' => 'GAL'], 'galatak' => ['' => 'GAL'], 'efezus' => ['' => 'EPH'], 'efezusiak' => ['' => 'EPH'],
        'filippi' => ['' => 'PHP'], 'filippiek' => ['' => 'PHP'], 'kolossze' => ['' => 'COL'], 'kolosszeiek' => ['' => 'COL'],
        'tesszalonika' => [1 => '1TH', 2 => '2TH'], 'tesszalonikaiak' => [1 => '1TH', 2 => '2TH'],
        'timoteus' => [1 => '1TI', 2 => '2TI'], 'titusz' => ['' => 'TIT'], 'titus' => ['' => 'TIT'], 'filemon' => ['' => 'PHM'],
        'zsidok' => ['' => 'HEB'], 'jakab' => ['' => 'JAS'], 'peter' => [1 => '1PE', 2 => '2PE'], 'judas' => ['' => 'JUD'],
        'jelenesek' => ['' => 'REV'], 'apokalipszis' => ['' => 'REV'],
    ];

    /** Words around a book name that only say it is a book: "Dániel könyve", "Szent Pál levele a rómaiakhoz". */
    public const BOOK_WORDS = [
        'konyve', 'konyv', 'konyvebol', 'level', 'levele', 'levelebol', 'evangeliuma', 'evangelium', 'evangeliumabol',
        'szerint', 'szerinti', 'irt', 'irott', 'szent', 'pal', 'apostol', 'apostolnak', 'a', 'az',
    ];
}
