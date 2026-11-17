<?php

/*
301	Korpuszcím
320	Belső korpusz címe
330	Könyvcím
340	Belső korpusz bevezetője
501	Címsor1
601	Címsor2
650	Címsor3
660	Zsoltárcím
665	Héber betűnév
670	Címsor4
680	Címsor5
701	Címsor6
850	Kihúzott sor
890	Kimaradó versszám (nullás)
891	Különlegesen írt versszám
892	Magyarázat a folyamatos szövegben (dőlt betűs)
900	2018 előtti szöveg
901	Netre kitett versszöveg
902	Idézett verssor (dőlt betűs)
903	Keresztidézet (dőlt betűs)
904	A következővel összevont vers
905	Verssor (álló betűs)
918	2019-es revízióval javított címsor
920	Kereszthivatkozás
950	Eltérő, egysoros teljes vers (pl. az ujszov.hu-ra)
1990	Több soros lábjegyzet teljes hivatkozása
1995	Szinoptikus párhuzam teljes hivatkozása
2001	Lábjegyzet a neten és a könyvben
2002	Több soros lábjegyzet szövege
2003	Több soros lábjegyzet zárósora
2004	Ismételt lábjegyzet, csak a neten
2018	2019-es revízióval javított lábjegyzetszöveg
2023	Lábjegyzet csak a könyvben
2024	Lábjegyzet csak a neten

*/

return [
    'definitions' => [
        'KNB' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [301=>0, 320=>1, 330=>2, 640=>3, 650=>4, 680=>5, 701=>6, 702=>7],
                'footnote' => [120, 2001, 2002],
                'poemLine' => [902],
                'xref' => [920],
                'footnoteInterval' => [1990]
            ],
            'textSource' => env('TEXT_SOURCE_KNB', 's3'),
            'id' => 3,
            'order' => 1,
            'copyright' => 'A Szent Jeromos Katolikus Bibliatársulat ideiglenes engedélyével. (Megújítva: 2025. április)',
            'commonName' => 'Káldi-Neovulgáta',
            'publisher' => [ 'name' => 'Szent Jeromos Katolikus Bibliatársulat', 'url' => 'https://www.biblia-tarsulat.hu'],
            'toc_heading_levels' => '5-9'
        ],

        'KG' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [1=>1, 2=>2, 3=>3],
                'xref' => [2017],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_KG', 's3'),
            'id' => 4,
            'order' => 11,
            'copyright' => 'A szöveg nem jogvédett.',
            'commonName' => 'Károli Biblia',
            'publisher' => [ 'name' => '', 'url' => 'https//theword.net'],
            'toc_heading_levels' => '5-9'

        ],
        // based on xml sources from 2026-02-25
        'SZIT' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [3000 => 0, 3001=>1, 3002=>2, 3003=>3, 3004=>4, 3005=>5, 3006=>6],
                'footnote' => [2001],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_SZIT', 's3'),
            'id' => 1,
            'order' => 3,
            'copyright' => 'A Szent István Társulat Szentírás-Bizottságának fordítása, új bevezetőkkel és magyarázatokkal; sajtó alá rendezte Rózsa Huba.  (Megújítva: 2025. február).',
            'commonName' => 'Szent István Társulati Biblia',
            'publisher' => [ 'name' => 'Szent István Társulat', 'url' => 'https://szitkonyvek.hu/'],
            'toc_heading_levels' => '4-9'
        ],
        'UF' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [703 => 3],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_UF', 's3'),
            'publisher' => [ 'name' => 'Magyar Bibliatársulat', 'url' => 'https://bibliatarsulat.hu'],
            'id' => 2,
            'order' => 10,
            'toc_heading_levels' => '5-9'
        ],
        'BD' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [701=>4, 704=>5],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_BD', 's3'),
            'id' => 5,
            'order' => 5,
            'copyright' => 'A Bencés Kiadó engedélyével. (Megújítva: 2025. február).',
            'commonName' => 'Békés–Dalos Újszövetség',
            'publisher' => [ 'name' => 'Bencés Kiadó', 'url' => 'https://www.benceskiado.hu'],
            'toc_heading_levels' => '5-9'

        ],
        'RUF' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [701=>3],
                'footnote' => [2001],
                'xref' => [2003],
                'poemLine' => [904]
            ],
            'textSource' => env('TEXT_SOURCE_RUF', 's3'),
            'id' => 6,
            'order' => 9,
            'copyright' => 'A 2014-es revidált Bibliát a Magyar Bibliatársulat ideiglenes engedélyével publikáljuk. A hivatalos változat http://abibliamindenkie.hu/ oldalon látható.',
            'commonName' => 'Újfordítású Biblia 2014',
            'publisher' => [ 'name' => 'Magyar Bibliatársulat', 'url' => 'https://bibliatarsulat.hu'],
            'toc_heading_levels' => '5-9'

        ],
        'STL' => [
            'verseTypes' =>
            [
                'text' => [901],
                'footnote' => [2001, 2004, 2023],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_STL', 's3'),
            'id' => 7,
            'order' => 4,
            'copyright' => 'A Bencés Kiadó engedélyével. (Megújítva: 2025. február).',
            'commonName' => 'Simon Tamás László Újszövetség',
            'publisher' => [ 'name' => 'Bencés Kiadó', 'url' => 'https://www.benceskiado.hu'],
            'toc_heading_levels' => '5-9'
        ],
        'GNT' => [
            'verseTypes' => [
                'text' => [901],
                'poemLine' => [902]
            ],
            'textSource' => env('TEXT_SOURCE_GNT', 's3'),
            'id' => 8,
            'order' => 99,
            'copyright' => 'Open Greek New Testament (OGNT) project, CC BY-SA 4.0.',
            'commonName' => 'Görög Újszövetség',
            'publisher' => [ 'name' => 'OpenGNT Project', 'url' => 'https://github.com/eliranwong/OpenGNT'],
            'toc_heading_levels' => '5-9'
        ],
        'KAL' => [
            'verseTypes' => [
                'text' => [901],
                'heading' => [701 => 3],
                'footnote' => [2001]
            ],
            'textSource' => env('TEXT_SOURCE_KAL', 'local'),
            'id' => 9,
            'order' => 12,
            'copyright' => 'Káldi György SJ 1626-os fordításának Tárkányi Béla által elvégzett revíziója, 1865 (nem jogvédett)',
            'commonName' => 'Káldi Biblia 1626',
            'publisher' => [ 'name' => 'Káldi Biblia Gitlab Projekt', 'url' => 'https://kaldibiblia.gitlab.io/'],
            'toc_heading_levels' => '5-9'
        ]
    ],
    'ids' => [
        1 => 'SZIT',
        2 => 'UF',
        3 => 'KNB',
        4 => 'KG',
        5 => 'BD',
        6 => 'RUF',
        7 => 'STL',
        8 => 'GNT',
        9 => 'KAL'
    ]
];
