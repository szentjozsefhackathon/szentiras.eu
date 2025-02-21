Szentírás.eu
========

> [!WARNING]
> **2025 januárjában átvette ennek a kódnak a helyét a [szentiras.hu](http://szentiras.hu) oldalon egy új (zárt) kód!**
> Az új helyen elveszett az API, az url-ek, a pdf generálás, a gyors kereső, stb., és koncepcionális változások is történtek.
> Így jelenleg (és valószínűleg a jövőben) ez a repo más irányt vesz.
> A változás oka részben az, hogy ennek a site-nak a rendszeres karbantartása és frissítése nem volt jól megoldott.

> [!IMPORTANT]
> Milyen funkciók frissítése hiányzik nálunk, ami az új site-on viszont megvalósult?
> - **import** - A komplex, cloud-ban található forrásfájlok egy gombnyomásos importálása. Így sok-sok szöveghibát hiába javították ki az excelben, nem jelent meg az oldalon. Az új site szövegforrása egy speciális XML formátum.
> - **lábjegyzetek és előszavak** - Ennek megvolt egy része, de ez szorosan összefügg az előző ponttal.
> - **további fordítások** - Ez szintén az első ponton múlik.
> - **Frissített szerver** - Az új site frissített szerveren fut.
> Más funkciója az új site-nak jelenleg nincs.
>
> A régi oldalt életben tartjuk a [regi.szentiras.hu](http://regi.szentiras.hu) címen, de ha sikerül új szervert találnunk és a megfelelő szövegfelhasználási engedélyeket megszereznünk, akkor ismét önállóan létezni fog az oldal, az eredeti koncepció szerint.

A [szentiras.hu](http://szentiras.hu) 2025 előtti változatának teljes kódja.

Fejlesztői környezet használata: [docker/Readme.md](docker/Readme.md)

------
Az alábbi információk elavultak.

## Felépítés

### Framework
A Laravel keretrendszert használjuk.

#### Könyvtár struktúra
- **app** - Maga a webalkalmazás.
- **bootstrap** - Framework beizzítás
- **deploy** - deployment konfigurációk és scriptek (apache, git hook stb.)
- **old** - a régi, össze-vissza programozott szentiras.hu kódja
- **public** - a közvetlenül kiszolgált fájlok, az index.php, css, js és hasonlók
- **tmp** - tesztadatbázis-példa

## Alapvető funkciók

### Főoldal:
- fordítások listája, néhány információval kiegészítve
- igenaptár adott napi megjelenítése a katolikus.hu/igenaptar-ból kihalászva minden alkalommal
- mysql-ből hírek (szerkesztő felület nélkül)

### Könyvnézegetés
- Fordítás könyveinek listája
- Egy-egy könyv fejezeteinek listája bevezetővel
- Egy-egy fejezet megjelenítése
    - Lépés a következő/előző fejezetre

### Idézés
- Url-be vagy keresőbe beírt szentírás hivatkozás észrevétele, elemzése és megjelenítése. pl. Mk3,4-7.9-12 vagy Mk3-6 vagy Mk 5,3-7,4.7 stb.
- Adott igehely/rész megjelenítése lábjegyzettel és magyarázattal - PARTLY DONE Egyelőre a lábjegyzet és magyarázat nem támogatott
- Link a többi fordítás azonos könyvére (sokszor már a rövidítés!)
- Hibás fordítás/könyvrövidítés estén átvisz a helyes kombinációra

### Szövegmegjelenítés - Idézésnél és fejezet nézetnél is
- Vannak címsorok, alcímsorok és a szöveg
- A vers száma felső indexben nem mindig a tényleges sorszám (akár betű is lehet)
- Twitter és facebook megosztás (hozzávaló html tag-ek, hogy szebb legyen a megosztott anyag)
- Link a többi fordítás azonos könyvére (sokszor már a rövidítés!)
- Lábjegyztekkel és magyarázatokkal. Ezek nem versekhez, hanem verstartományokhoz tartoznak.(Éppen nem működik, mert nincs az adatbázisban.)

### Keresés
- Url-be vagy keresőbe beírt kifejezés keresése
- szűrési lehetőség: bármely könyvre ill. Újszöv/Ószöv; fordítás
- találatok csoportosítása: versenként vagy fejezetenként
- A találatokat súlyozza és a legsúlyosabbal kezdi (tehát nem előfordulási könyvbeli sorrendben jelennek meg)
- Amit figyel
    - Sphinx extended search nyelvtan szerint
    - szótőben keres (megkeresi a hunspell a keresőszó szótöveit, a szentírás minden versét szótöves változatban is tároljuk) PARTLY DONE (a keresőszót nem tövezzük)
- Extrák
    - megnézni, hogy az alternatív fordításokban ugyan erre mennyi a találat - csak a Sphinx indexben nézi, ezért villámgyors
    - tippeket ad hasonló kifejezésekre ill. azonos szótövű szavakra
    - tippeket ad kézzel készülő szinoníma szótár alapján: létra/lajtorja, Nebukodonozor/Nebukadanazzár, stb.
    - ugyan ezen szótár szerint elgépelt nevekre is tippeket ad

### API/Fejlesztőknek
- JSON válaszokat ad megfelelő kérésekre
- főként a keresésekre válaszol és elvileg mindent tudnia kéne, amit a keresőnek, csak más formátumban válaszol
- TODO: könyvek automatikus /epub kimenetele ill. abból generált /mobi (havonta egyszer gyárt, onnantól mindig azt hivatkozza)

### Egyéb
- pár statikusabb oldal van (címlap, infó, impresszum, stb.) (nem kell szerkesztő felület)
- cache: van egy csomó cache-elés, főleg db
- mysql cache: a kereséseket logolja és cacheli, hogy az alternítvákat jól mutassa és lássam, mi megy az életben DONE
- rövid urlek (és régi-régi linkek hosszú url-jeit is értelmezi)
- GoogleAnalytics
