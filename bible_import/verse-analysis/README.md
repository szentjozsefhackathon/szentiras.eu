# Görög versenkénti elemzés

Fejezetenként egy JSON fájl (`{USX_CODE}_{chapter}.json`, pl. `JHN_3.json`),
amely az adott fejezet minden verséhez tartalmazza a görög szöveget és annak
szakaszokra (szó vagy szókapcsolat) bontott magyar jelentéseit.

Az elemzések generált állományok, ezért nem kerülnek verziókezelésbe. A
kanonikus Laravel storage-kulcsuk:

```
greek/verse-analysis/OpenGNT/hu/v1/{USX_CODE}_{chapter}.json
```

Ez lokálisan a `local` disk alatt, vagyis a
`storage/app/private/greek/verse-analysis/OpenGNT/hu/v1` könyvtárban található.
Ugyanezt a kulcsot használjuk az S3 disk esetén is.

## Generálás és ellenőrzés

A driver először tömör forrásfájlokat exportál, a Claude-tól csak a szemantikai
szegmentálást kéri, majd a teljes elemzési JSON-t helyben, determinisztikusan
állítja össze. A formátum és a szabályok a
`resources/prompts/greek_verse_analysis_system.md`, a hívások rövidített
utasításai pedig a `resources/prompts/greek_verse_analysis_semantic.md`
fájlban vannak.

Több fejezet feldolgozásakor a driver a validátor adatbázis-alapú JSON
eredményéből, kanonikus sorrendben választja ki a hiányzó vagy hibás
fejezeteket. A fejezeteket vershatáron darabolja, és minden hiányzó darabhoz
egyetlen, munkamenet nélküli `claude -p` folyamatot indít:

```
bash bible_import/verse-analysis/gorog-elemzes-driver.sh Jn
bash bible_import/verse-analysis/gorog-elemzes-driver.sh 1Tim 2Tim
```

A köztes fájlok a
`storage/app/private/greek/verse-analysis/work/{USX_CODE}_{chapter}`
könyvtárban, a futási naplók pedig a
`storage/app/private/greek/verse-analysis/logs` könyvtárban maradnak. Egy
megszakított futás az érvényes `semantic-*.json` ellenőrzőpontokat újra
felhasználja.

Ellenőrzés (ez a garancia arra, hogy a fájlok később importálhatók):

```
php artisan szentiras:validate-verse-analysis               # a teljes Újszövetség
php artisan szentiras:validate-verse-analysis "Jn 3"        # egy fejezet
php artisan szentiras:validate-verse-analysis Jn --missing  # a még hiányzó fejezetek
```

A `--dir` abszolút útvonalat vagy a Laravel `local` disk gyökeréhez képest
relatív könyvtárat fogad.

Környezeti változókkal módosítható:

- `VERSE_ANALYSIS_MODEL` (alapértelmezés: `opus`)
- `VERSE_ANALYSIS_EFFORT` (alapértelmezés: `medium`)
- `VERSE_ANALYSIS_CHUNK_WORDS` (alapértelmezés: `180`)
- `VERSE_ANALYSIS_CREATED_BY`

Az export és az összeállítás külön is futtatható:

```
php artisan szentiras:export-verse-analysis-context "Jn 1"
php artisan szentiras:assemble-verse-analysis \
    greek/verse-analysis/work/JHN_1/manifest.json
```

## Import a lokális adatbázisba

Az import alapértelmezésben a `local` diskről és a fenti kanonikus
storage-kulcs alól olvas. Elsőként érdemes írás nélküli ellenőrzést futtatni:

```
php artisan szentiras:import-verse-analysis --dry-run
php artisan szentiras:import-verse-analysis
```

A `--prune` kapcsoló törli az adatbázisból azokat a korábban e path alól
importált verselemzéseket, amelyek a jelenlegi állományokból már hiányoznak:

```
php artisan szentiras:import-verse-analysis --prune
```

Más verzió vagy nyelv a `--path` és `--locale` kapcsolóval adható meg.

## Feltöltés S3-ra és production import

Az S3 bucket közös az alkalmazás többi anyagával; a Laravel `s3` disk a
`.env` `AWS_BUCKET` értékét használja. Az AWS CLI nem olvassa automatikusan a
Laravel `.env` fájlt, ezért az alábbi parancsban az `<AWS_BUCKET>` helyére
ennek az értékét kell írni:

```
aws --profile xxx s3 sync \
    storage/app/private/greek/verse-analysis/OpenGNT/hu/v1/ \
    s3://<AWS_BUCKET>/greek/verse-analysis/OpenGNT/hu/v1/
```

A záró `/` miatt a könyvtár tartalma közvetlenül a `v1/` prefix alá kerül. A
work fájlok és a naplók nem töltődnek fel.

Productionön ugyanaz az import parancs fut, csak az S3 disket kell átadni:

```
php artisan szentiras:import-verse-analysis --disk=s3 --dry-run
php artisan szentiras:import-verse-analysis --disk=s3
```
