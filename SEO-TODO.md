# SEO / GEO TODO

Tracks what we do to rank higher in Google and to be quoted by AI assistants (GEO).

## Baseline (2026-11-17)

Google Search Console, web search, 2026-08-16 – 2026-11-15:

| Month | Clicks | Impressions |
|---|---|---|
| 2026-09 | 30,692 | 435,440 |
| 2026-10 | 34,751 | 494,907 |

- Last 4 weeks: +16% clicks compared with the first 4 weeks of the export.
- Hungary 77% of clicks, Romania 13%, Slovakia 3%. Mobile 63%.
- Key queries:

  | Query | Impressions | Position | CTR |
  |---|---|---|---|
  | biblia | 14,877 | 9.4 | 0.9% |
  | biblia online | 7,844 | 6.0 | 1.6% |
  | szentírás | 6,896 | 2.3 | 10% |
  | jelenések könyve | 3,940 | 7.9 | 2.3% |
  | zsoltárok | 3,609 | 5.8 | 5.5% |
  | 23 zsoltár | 1,885 | 6.1 | 5.4% |
  | az úr az én pásztorom | 1,911 | 4.0 | 5.8% |
  | 91 zsoltár | 1,206 | 7.0 | 0.7% |
  | máté 11:28 | 842 | 7.1 | 0.7% |

- External estimates for October 2026 (rough, about ±50%):
  - **Similarweb:** szentiras.eu about 46.5K visits, szentiras.hu about 73K.
  - **Semrush:** szentiras.hu about 115K visits.
  - **Calibration:** Search Console shows about 35K Google clicks for szentiras.eu alone, so Similarweb probably undercounts by 1.3–1.5×.

## Findings

- **Chapter pages compete with each other.** Every translation of a chapter is a separate page whose canonical URL points to itself, so Google splits the signals between them. Zsolt 23 has 8,283 impressions on KNB, 7,681 on KG, 2,083 on SZIT and 884 on RUF, at positions 4–6.4.
- **People search for passages by name or by their opening words.** Examples: "az úr az én pásztorom", "hegyi beszéd", "a szeretet nem kérkedik", "kezdetben volt az ige". No page targets these yet.
- **People search for books by their popular name.** Examples: "Máté evangéliuma", "jelenések könyve". Our titles use the official names, e.g. "Az evangélium Máté szerint". Book searches had 40K impressions and 2.3K clicks, mostly at positions 5–10.
- **Sunday gospel references already rank #1–2.** Examples: "mt 22,34-40", "mt 25,14-30".

## Done

- [x] **2026-11-17 Homepage title and description** now target "Biblia online", with "Szentírás" kept in the title. (`HomeController`)
- [x] **2026-11-17 Book introductions** from the publishers' XML sources for SZIT (42), KNB (78) and BD (29).
  - Every book has at least one introduction.
  - They are shown collapsed on book pages, and chapter pages link to them.
  - Import command: `szentiras:import-book-introductions {SZIT|KNB|BD}`.
  - The XML sources are on S3 under `xml/` (KNB, BD and STL uploaded 2026-11-17).

- [x] **2026-11-17 Popular book names** (`BookCommonNames`, one list per USX code for all 73 books).
  - Book page `<title>` leads with the popular name and keeps the official one, e.g. "Máté evangéliuma – bevezetés és teljes szöveg | Az evangélium Máté szerint (SZIT)". When the official name already is the popular one, it leads.
  - A "Más néven" line under the official H1.
  - Meta description: names, translation, then the first paragraph of the book introduction.
  - Book pages got a canonical URL and `Book` structured data with `alternateName`.
  - Quick search also understands "Mózes első könyve" and multi-word names like "Apostolok cselekedetei" (`ReferenceDetector`).

- [x] **2026-11-17 Chapter page titles** lead with what people type and name the translation the way it is searched (`commonName` in `config/translations.php`).
  - "23. zsoltár – Károli Biblia (KG)", "Máté evangéliuma 5. fejezet – Szent István Társulati Biblia (SZIT)".
  - Protestant translations keep their own book names ("Mózes első könyve 1. fejezet – Újfordítású Biblia 2014 (RUF)").
  - The H1 keeps the official name; the meta description starts with the same name plus the reference.
  - Verse-range pages ("Mt 5,3-12") are unchanged.
  - Baseline for "23 zsoltár" (3 months to 2026-11-15): Google mostly showed `/KNB/Zsolt23` (1,838 impressions, position 6.8, 1.6% CTR), rarely `/SZIT/Zsolt23` (283 impressions, position 3.0, 23.7% CTR). Re-export after deploying.

## To do

### Next

- [x] **2026-11-17 Deployed** the homepage title, book introductions (imported for SZIT, KNB and BD), popular book names and chapter titles.
- [ ] **Watch Search Console** from about 2026-12-01: the book queries ("jelenések könyve", "zsoltárok", "példabeszédek", "énekek éneke") and "23 zsoltár" (re-export and compare with the baseline above).

### Famous passage pages (`/igehely/{slug}`)

- [ ] **Data model and editor.** Use the same pattern as guides. Fields: name, slug, alternative names, reference, introduction, Markdown body, SEO title and description.
- [ ] **What each page shows:**
  - the text in the default translation (SZIT), with tabs for the other translations;
  - the key verse in every translation side by side;
  - Greek key words (New Testament only);
  - when the passage is read in the liturgy, worked out from the daily-readings data;
  - context, common questions, related passages, verse card and PDF.
- [ ] **Internal links:** a banner on the chapter page ("Ez a rész ismert mint …"), a homepage tile, an entry in the sitemap, and quick search by name.
- [ ] **First batch,** ordered by Search Console demand:
  1. Zsolt 23
  2. Mt 5 (Hegyi beszéd, Boldogmondások)
  3. 1Kor 13 (Szeretethimnusz)
  4. Mt 6 (Miatyánk)
  5. Jn 14
  6. Zsolt 91
  7. Ter 1
  8. Mt 11,28
  9. Jn 1,1-18
  10. Préd 3
- [ ] **Second batch:** Róm 8, Jn 10, Jn 15, Jer 29,11, Lk 15, Lk 2, Zsolt 90.
- [ ] **Content workflow:** AI draft from the real text and Greek analysis, then human review before publishing.

### Later

- [ ] **Themed collections:** igék esküvőre, temetésre, keresztelőre, bérmálásra, vigasztaló igék.
- [ ] **Links in introductions.** Turn plain-text references in the SZIT and BD introductions (e.g. "(3,1-12)") into links.
- [ ] **AI assistants.** Expose the book introductions through the MCP connector, with a credit line.
- [ ] **Return visits:** daily-reading email or push notifications.

### Ongoing

- [ ] **Hungarian Wikipedia.** Move `szentiras.hu` links to `szentiras.eu` by hand. Disclose the conflict of interest; for templates such as `Sablon:Biblia hivatkozás`, propose the change on the talk page.
- [ ] **Broken legacy links.** szentiras.hu returns 404 for `/kereses`, `/tervek`, `/forditasok`, `/SZIT` and the old `/API/?feladat=…`. Ask the sites linking there to switch the domain to szentiras.eu.
- [ ] **API users.** Contact public projects that call the szentiras.hu API: `molnarm/igemutato`, `reformatus/convert-scripts`, `csgellert/bible_reading`, `aporkolab/BibleVerse`, `dcm-c/sutabot`.
- [ ] **Monthly check.** Export from Search Console: the queries above, plus clicks and impressions for the new pages.
