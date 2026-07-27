Készíts az Újszövetség egy fejezetének görög szövegéhez versenkénti, szakaszokra bontott magyar elemzést. Az elemzés célja, hogy a görög szöveget olvasó magyar felhasználó szóról szóra kövesse az eredetit, a szövegkörnyezetnek megfelelő jelentésekkel — nem kommentárt, nem magyarázatot, hanem pontos tükörfordítást szókapcsolatokra bontva.

## Forrás

- A görög szöveget, a szavak sorrendjét és indexét, a Strong-számokat és a szótári első jelentést a `get-greek-verses` eszköz adja. Soha ne idézz görög szöveget emlékezetből.
- A magyar megoldásokhoz a `get-verses` eszközt hívd meg a SZIT, KNB, STL és RUF fordításra ugyanarra a hivatkozásra. Soha ne idézz magyar bibliafordítást emlékezetből.
- Ha egy szó első szótári jelentése nem illik a szövegkörnyezetbe, a `lookup-greek-word` eszközzel kérd le a Strong-szám teljes szócikkét, és annak jelentései közül válassz.

## A kimenet formátuma

Egy fejezet = egy JSON fájl, `{USX_CODE}_{chapter}.json` néven:

```json
{
  "format": 1,
  "usxCode": "JHN",
  "book": "Jn",
  "chapter": 3,
  "greekSource": "OpenGNT",
  "createdBy": "claude-opus-4-8",
  "createdAt": "2026-07-24",
  "verses": [
    {
      "verse": 16,
      "gepi": "JHN_3_16",
      "greekText": "Οὕτως γὰρ ἠγάπησεν ὁ Θεὸς τὸν κόσμον,",
      "segments": [
        { "wordIndexes": [0, 1], "greek": "Οὕτως γὰρ", "meaning": "úgy ugyanis", "alternatives": ["mert így"] },
        { "wordIndexes": [2], "greek": "ἠγάπησεν", "meaning": "szerette" },
        { "wordIndexes": [3, 4], "greek": "ὁ Θεὸς", "meaning": "az Isten" },
        { "wordIndexes": [5, 6], "greek": "τὸν κόσμον,", "meaning": "a világot" }
      ]
    }
  ]
}
```

Mezőszabályok:

- `verses` — a fejezet **összes** verse, sorrendben, kihagyás és többlet nélkül. A versszámok és a `gepi` a `get-greek-verses` válaszából valók.
- `greekText` — a `get-greek-verses` `greekText` mezője **betű szerint**, változtatás nélkül (írásjelekkel együtt).
- `wordIndexes` — a `get-greek-verses` `i` mezői. Egy versen belül a szakaszok **pontosan egyszer** fedik le a `0..n-1` indexeket; a szakaszok a saját első indexük szerint növekvő sorrendben állnak, és a szakaszon belül is növekvők az indexek.
- `greek` — a `wordIndexes`-hez tartozó `printed` alakok szóközzel összefűzve, **betű szerint**, írásjelekkel együtt (`"κόσμον,"`). Ezt gép ellenőrzi, ezért egyetlen karakter sem térhet el.
- `meaning` — a szókapcsolat magyar jelentése a szövegkörnyezetben, ragozva.
- `alternatives` (opcionális) — sztringek tömbje, zárójel és magyarázat nélkül.
- `note` (opcionális, ritka) — egyetlen rövid mondat, csak ha a szakasz e nélkül félreérthető.

## Csoportosítás

Egy szókapcsolat a lehető legkisebb egység legyen: alapesetben egy szó. Csak akkor vonj össze több szót, ha a magyar megoldás nem bontható szét értelmesen:

- névelő + főnév (`ὁ Θεὸς` → „az Isten")
- prepozíció + vonzata (`εἰς τὸν κόσμον` → „a világba")
- tagadószó + ige (`οὐ πιστεύει` → „nem hisz")
- kopula + névszói állítmány
- birtokos szerkezet, ha magyarul egyetlen szerkezetté áll össze
- állandósult fordulatok (`ἀπεκρίθη καὶ εἶπεν` → „válaszolt és mondta" — ha a fordítások egyben kezelik)

Nem összefüggő indexek (pl. `[0, 2]`) csak akkor, ha a görög szórend miatt elkerülhetetlen; az ellenőrző parancs figyelmeztet rájuk.

## Jelentésadás

- A kiindulás a `get-greek-verses` `meaning` mezője (a szószedet első jelentése).
- Ha ez a szövegkörnyezetbe nem illik, `lookup-greek-word` a szó Strong-számára, és a szócikk jelentései közül válaszd a kontextusba illőt.
- A szóválasztásban a négy fordítás (SZIT, KNB, STL, RUF) megoldása a döntő. Ha a négy fordítás egyetért, ne találj ki ötödik változatot.
- A `meaning` a magyar mondatba illő, ragozott alak legyen, ne szótári alak.

## Alternatívák

- `alternatives` akkor kell, ha a négy fordítás **érdemben** eltér: más szótári jelentést választanak, más a szerkezet értelmezése (pl. `μετάνοια` → „megtérés" / „bűnbánat"), vagy egy idiómát, sémi fordulatot, kétértelmű szerkezetet mindegyik másképp old fel (pl. `Τί ἐμοὶ καὶ σοί` → „mit akarsz tőlem" / „mi közünk ehhez" / „rám tartozik ez, vagy rád").
- **Ez az `alternatives` legfontosabb szerepe: ahol a négy fordítás értelmezése szétszór, ott kötelező felvenni az eltérő olvasatokat — ezt soha ne hagyd ki.** A `meaning` az egyik olvasat marad, a többi érdemben különböző olvasat az `alternatives`-be kerül.
- A `note` és az `alternatives` **kiegészítik egymást, nem helyettesítik**: a szó szerinti jelentést adó `note` nem mentesít az eltérő fordítói olvasatok `alternatives`-be vétele alól.
- Stiláris szinonima, szórendi vagy ragozási eltérés nem alternatíva.
- Fordításonként legfeljebb egy változat, duplikátum nélkül (az azonos értelmezésű változatokat vond össze), és az `alternatives` soha nem ismétli meg a `meaning` értékét.

## Tilalmak

- Ne idézz görögöt vagy magyar bibliafordítást emlékezetből — mindig eszközből dolgozz.
- Ne írj kommentárt, teológiai magyarázatot, prédikációt.
- Ne hagyj ki verset, és ne told át egy vers szavait a szomszédos versbe.
- Ne alakítsd át, ne normalizáld, ne ékezetesítsd újra a `greekText` és a `greek` mezőket.
- Ne írj a JSON fájlba a formátumban nem szereplő mezőt.
