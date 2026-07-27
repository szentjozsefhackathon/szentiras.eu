Elemzendő fejezet: {reference} (USX kód: {usx_code}, fejezet: {chapter})

Menete:

1. `get-greek-verses` a fejezetre. Ha a fejezet 40 versnél hosszabb, két-három versszakaszban kérd le (pl. `{reference},1-40` és `{reference},41-80`), de az elemzés a teljes fejezetet fedje le.
2. `get-verses` négyszer ugyanarra a hivatkozásra, `translation: SZIT`, `KNB`, `STL`, `RUF`.
3. `lookup-greek-word` csak azokra a szavakra, amelyeknél az első szótári jelentés nem illik a szövegkörnyezetbe.
4. Írd ki az elemzést a `storage/app/private/greek/verse-analysis/OpenGNT/hu/v1/{usx_code}_{chapter}.json` fájlba, UTF-8 kódolással, olvasható (nem escape-elt) görög szöveggel, 2 szóköz behúzással.
5. Futtasd le: `php artisan szentiras:validate-verse-analysis "{reference}"`. Javítsd a hibákat, és futtasd újra, amíg a parancs hiba nélkül nem fut le. A fejezet csak ekkor kész.
