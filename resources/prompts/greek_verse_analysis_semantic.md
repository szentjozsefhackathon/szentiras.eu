Készíts kizárólag szemantikai szakaszolást a standard bemeneten kapott görög
versszakaszhoz. Ne másold vissza a görög szöveget, és ne írj fájlt: a helyi
összeállító a szóindexekből determinisztikusan tölti ki a görög mezőket.

A bemenetben a `words` minden eleme `[printed, strongNumber, morphology]`; a
tömbbeli helye a szó nullától induló indexe. A `dictionary` Strong-számonként a
használható magyar szótári jelentéseket, a `morphology` a kódok magyar leírását,
a `translations` pedig a SZIT, KNB, STL és RUF versszövegeit tartalmazza. A
`contextBefore` és `contextAfter` csak szövegkörnyezet, ezekhez ne adj kimenetet.

Szabályok:

- Minden bemeneti vers pontosan egyszer, eredeti sorrendben szerepeljen.
- A szakaszok pontosan egyszer fedjék le a vers minden szóindexét.
- A szakaszok és a bennük lévő indexek növekvő sorrendűek legyenek.
- Alapesetben egy görög szó legyen egy szakasz. Csak magyarul felbonthatatlan
  egységeket vonj össze: névelő és főnév, prepozíció és vonzata, tagadószó és
  ige, kopula és névszói állítmány, összetartozó birtokos szerkezet vagy
  állandósult fordulat.
- A `meaning` a mondatba illő, ragozott magyar jelentés legyen. A szótárból és
  a négy fordításból dolgozz; ne írj kommentárt vagy teológiai magyarázatot.
- Ha a négy fordítás érdemben eltérő jelentést vagy szerkezeti olvasatot ad,
  a többi olvasat kötelezően kerüljön az `alternatives` tömbbe. Puszta
  stiláris, szórendi vagy ragozási eltérés nem alternatíva. Ne ismételd meg a
  `meaning` értékét, és ne adj duplikátumot.
- `note` csak ritkán, egyetlen rövid mondatként szerepeljen, ha nélküle a
  szókapcsolat félreérthető.

Csak a megadott JSON-sémának megfelelő adatot add vissza.
