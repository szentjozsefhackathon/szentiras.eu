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
            'publisher' => [ 'name' => 'Szent Jeromos Katolikus Bibliatársulat', 'url' => 'https://www.biblia-tarsulat.hu']
        ],

        'KG' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [1=>1, 2=>2, 3=>3],
                'xref' => [2017]
            ],
            'textSource' => env('TEXT_SOURCE_KG', 's3'),
            'id' => 4,
            'order' => 11,
            'copyright' => 'A szöveg nem jogvédett.',
            'publisher' => [ 'name' => '', 'url' => 'https//theword.net']

        ],
        'SZIT' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [401=>2, 501=>4, 601=>4, 701=>5, 704=>6],
                'footnote' => []
            ],
            'textSource' => env('TEXT_SOURCE_SZIT', 's3'),
            'id' => 1,
            'order' => 3,
            'copyright' => 'A Szent István Társulat Szentírás-Bizottságának fordítása, új bevezetőkkel és magyarázatokkal; sajtó alá rendezte Rózsa Huba.  (Megújítva: 2025. február).',
            'publisher' => [ 'name' => 'Szent István Társulat', 'url' => 'https://szitkonyvek.hu/']
        ],
        'UF' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [703 => 3]
            ],
            'textSource' => env('TEXT_SOURCE_UF', 's3'),
            'publisher' => [ 'name' => 'Magyar Bibliatársulat', 'url' => 'https://bibliatarsulat.hu'],
            'id' => 2,
            'order' => 10,
        ],
        'BD' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [701=>4, 704=>5]
            ],
            'textSource' => env('TEXT_SOURCE_BD', 's3'),
            'id' => 5,
            'order' => 5,
            'copyright' => 'A Bencés Kiadó engedélyével. (Megújítva: 2025. február).',
            'publisher' => [ 'name' => 'Bencés Kiadó', 'url' => 'https://www.benceskiado.hu']

        ],
        'RUF' => [
            'verseTypes' =>
            [
                'text' => [901],
                'heading' => [701=>3],
                'footnote' => [2001],
                'xref' => [2021]
            ],
            'textSource' => env('TEXT_SOURCE_RUF', 's3'),
            'id' => 6,
            'order' => 9,
            'copyright' => 'A 2014-es revidált Bibliát a Magyar Bibliatársulat ideiglenes engedélyével publikáljuk. A hivatalos változat http://abibliamindenkie.hu/ oldalon  látható</a>.',
            'publisher' => [ 'name' => 'Magyar Bibliatársulat', 'url' => 'https://bibliatarsulat.hu']

        ],
        'STL' => [
            'verseTypes' =>
            [
                'text' => [901],
                'footnote' => [2001, 2004, 2023]
            ],
            'textSource' => env('TEXT_SOURCE_STL', 's3'),
            'id' => 7,
            'order' => 4,
            'copyright' => 'A Bencés Kiadó engedélyével. (Megújítva: 2025. február).',
            'publisher' => [ 'name' => 'Bencés Kiadó', 'url' => 'https://www.benceskiado.hu']
        ]
    ],
    'ids' => [
        1 => 'SZIT',
        2 => 'UF',
        3 => 'KNB',
        4 => 'KG',
        5 => 'BD',
        6 => 'RUF',
        7 => 'STL'
    ]
];
