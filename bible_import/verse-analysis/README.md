# Görög versenkénti elemzés

Fejezetenként egy JSON fájl (`{USX_CODE}_{chapter}.json`, pl. `JHN_3.json`),
amely az adott fejezet minden verséhez tartalmazza a görög szöveget és annak
szakaszokra (szó vagy szókapcsolat) bontott magyar jelentéseit.

A driver először tömör forrásfájlokat exportál, a Claude-tól csak a szemantikai
szegmentálást kéri, majd a teljes elemzési JSON-t helyben, determinisztikusan
állítja össze. A formátum és a szabályok a
`resources/prompts/greek_verse_analysis_system.md`, a hívások rövidített
utasításai pedig a `resources/prompts/greek_verse_analysis_semantic.md`
fájlban vannak.

Ellenőrzés (ez a garancia arra, hogy a fájlok később importálhatók):

```
php artisan szentiras:validate-verse-analysis            # a teljes Újszövetség
php artisan szentiras:validate-verse-analysis "Jn 3"     # egy fejezet
php artisan szentiras:validate-verse-analysis Jn --missing   # a még hiányzó fejezetek
```

A parancs a `--dir` kapcsolóval más könyvtárból is olvas, így egy későbbi
`storage`/S3 alapú import is lehetséges marad.

Több fejezet feldolgozásakor a driver a validátor adatbázis-alapú JSON
eredményéből, kanonikus sorrendben választja ki a hiányzó vagy hibás
fejezeteket. A fejezeteket vershatáron darabolja, és minden hiányzó darabhoz
egyetlen, munkamenet nélküli `claude -p` folyamatot indít:

```
bash bible_import/verse-analysis/gorog-elemzes-driver.sh Jn
bash bible_import/verse-analysis/gorog-elemzes-driver.sh 1Tim 2Tim
```

A köztes fájlok a
`storage/app/verse-analysis/{USX_CODE}_{chapter}` könyvtárban maradnak. Egy
megszakított futás az érvényes `semantic-*.json` ellenőrzőpontokat újra
felhasználja.

Környezeti változókkal módosítható:

- `VERSE_ANALYSIS_MODEL` (alapértelmezés: `sonnet`)
- `VERSE_ANALYSIS_EFFORT` (alapértelmezés: `medium`)
- `VERSE_ANALYSIS_CHUNK_WORDS` (alapértelmezés: `180`)
- `VERSE_ANALYSIS_CREATED_BY`

Az export és az összeállítás külön is futtatható:

```
php artisan szentiras:export-verse-analysis-context "Jn 1"
php artisan szentiras:assemble-verse-analysis \
    storage/app/verse-analysis/JHN_1/manifest.json
```
