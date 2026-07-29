Készíts koiné görög újszövetségi szótárt katolikus, nem szakértő magyar olvasók számára. A bemenet egy Strong-számhoz tartozó lexéma szótári alakja (lemma), nem egy bibliai mondatból kiemelt, ragozott szóalak. A válaszodat JSON-formátumban add meg.

A jelentéseket mindig a teljes lexémához, szótári címszóként add meg. Ne fordítsd le mechanikusan a görög lemma nyelvtani személyét, számát, idejét vagy módját. A görög szótárak az igéket rendszerint jelen idejű, kijelentő módú, egyes szám első személyű alakkal jelölik, de ez csak a görög címszó konvenciója:

- εἰμί szótári jelentése „van” (a „lenni” ige), nem „vagyok” vagy „én vagyok”;
- γεννάω szótári jelentése „nemz”, nem „nemzek”.

A magyar igéket a magyar szótári alakban, egyes szám harmadik személyben add meg. Személyhez vagy mondatbeli szövegkörnyezethez kötött fordítást csak a magyarázatban említs, ha az a használat megértéséhez szükséges. A `meanings` listában az Újszövetségben legáltalánosabb lexikai jelentés legyen az első.

A JSON szerkezete:

{
  "word": "a görög szótári alak (főnevek esetében: a lemma, a birtokos eset és a nem; a nemet zárójelben add meg magyar szóval: hímnem, nőnem vagy semlegesnem; igék esetében: semmi egyéb)",
  "meanings": [
    {
      "meaning": "magyar jelentés",
      "explanation": "Magyarázat magyarul."
    },
    {
      "meaning": "...",
      "explanation": "..."
    }
  ],
  "etymology": "Egy mondatos etimológia magyarul.",
  "notes": "Magyar nyelvű, katolikus tanításnak megfelelő megjegyzések, ha vannak fontos és jól megalapozott tudnivalók a szó újszövetségi használatáról."
}

A „meaning” mezőben mindig egyetlen, tömör szótári jelentés szerepeljen.
