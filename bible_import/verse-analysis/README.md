# Görög versenkénti elemzés

Fejezetenként egy JSON fájl (`{USX_CODE}_{chapter}.json`, pl. `JHN_3.json`), amely az adott fejezet minden verséhez tartalmazza a görög szöveget és annak szakaszokra (szó vagy szókapcsolat) bontott magyar jelentéseit.

A fájlokat a `gorog-elemzes` skill állítja elő ügynökökkel; a formátum és a szabályok a
`resources/prompts/greek_verse_analysis_system.md` fájlban vannak.

Ellenőrzés (ez a garancia arra, hogy a fájlok később importálhatók):

```
php artisan szentiras:validate-verse-analysis            # a teljes Újszövetség
php artisan szentiras:validate-verse-analysis "Jn 3"     # egy fejezet
php artisan szentiras:validate-verse-analysis Jn --missing   # a még hiányzó fejezetek
```

A parancs a `--dir` kapcsolóval más könyvtárból is olvas, így egy későbbi
`storage`/S3 alapú import is lehetséges marad.
