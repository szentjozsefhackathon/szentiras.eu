#!/usr/bin/env bash
#
# update-news.sh — Frissíti a hírek oldalt (news.twig) a legutóbbi frissítés
# óta történt, *felhasználót érintő* változások alapján, majd commitolja.
#
# A user-facing változásokat a `claude -p` (headless Claude Code) állapítja meg
# a git logból és diffből, és a meglévő stílushoz illő magyar hírblokkot ír.
#
# Használat:
#   scripts/update-news.sh [--dry-run] [--no-commit]
#
#   --dry-run    Csak kiírja a javasolt hírblokkot, nem módosít fájlt.
#   --no-commit  Módosítja a news.twig-et, de nem commitol.

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

NEWS_FILE="resources/views/info/news.twig"
MAX_DIFF_LINES=6000

DRY_RUN=0
NO_COMMIT=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --no-commit) NO_COMMIT=1 ;;
    *) echo "Ismeretlen kapcsoló: $arg" >&2; exit 2 ;;
  esac
done

command -v claude >/dev/null 2>&1 || { echo "Hiba: a 'claude' parancs nem található." >&2; exit 1; }
[ -f "$NEWS_FILE" ] || { echo "Hiba: $NEWS_FILE nem található." >&2; exit 1; }

# A legutóbbi commit, amely a news.twig-et módosította — innen nézzük a változásokat.
LAST_NEWS_COMMIT="$(git log -1 --format=%H -- "$NEWS_FILE")"
if [ -z "$LAST_NEWS_COMMIT" ]; then
  echo "Hiba: nem található korábbi news.twig commit." >&2
  exit 1
fi

RANGE="${LAST_NEWS_COMMIT}..HEAD"

# A tartományba eső, nem-merge commitok. Ha nincs ilyen, nincs mit tenni.
COMMIT_SUBJECTS="$(git log --no-merges --format='- %h %s' "$RANGE")"
if [ -z "$COMMIT_SUBJECTS" ]; then
  echo "Nincs új commit a legutóbbi hírfrissítés (${LAST_NEWS_COMMIT:0:9}) óta. Nincs teendő."
  exit 0
fi

TODAY="$(date +'%Y. %m. %d.')"

# Kontextus összeállítása Claude számára: commit-üzenetek, változott fájlok,
# és a (méretben korlátozott) teljes diff — a news.twig-et kihagyva.
CHANGED_FILES="$(git diff --stat "$RANGE" -- . ":(exclude)$NEWS_FILE")"
FULL_DIFF="$(git diff "$RANGE" -- . ":(exclude)$NEWS_FILE" | head -n "$MAX_DIFF_LINES")"

CONTEXT="$(cat <<EOF
COMMIT-ÜZENETEK (${RANGE}):
${COMMIT_SUBJECTS}

VÁLTOZOTT FÁJLOK:
${CHANGED_FILES}

TELJES DIFF (max ${MAX_DIFF_LINES} sor):
${FULL_DIFF}
EOF
)"

INSTRUCTIONS="A megadott git logot és diffet elemezve állapítsd meg a Szentírás.eu \
bibliaoldal FELHASZNÁLÓT ÉRINTŐ (user-facing) változásait a legutóbbi hírfrissítés óta.

Szabályok:
- Csak azt vedd figyelembe, ami a látogató/felhasználó számára érzékelhető: új funkció, \
látható viselkedés- vagy felületváltozás, új tartalom, teljesítmény- vagy hozzáférhetőségi \
javítás. HAGYD KI a belső refaktorokat, teszteket, build- és fejlesztői változásokat, \
kommentárokat, függőségfrissítéseket.
- Magyar nyelven, a meglévő hírbejegyzések stílusában és hangnemében írj (lásd a példát).
- 1-6 tömör, felsorolásos pont. A legfontosabb, kiemelendő szavakat **félkövérrel** jelöld. \
Ahol értelmes, használj belső hivatkozást (pl. [Görög Újszövetség](/GNT)).
- NE ismételj olyat, ami már szerepelhet korábbi hírként.

A pontos formátum, amit adj vissza (semmi mást, se magyarázatot, se kódblokk-jelölést):

**${TODAY}**
* <első pont>
* <további pontok>

Ha NINCS érdemi, felhasználót érintő változás, akkor pontosan ezt az egy sort add vissza:
NO_USER_FACING_CHANGES"

echo "» A(z) ${LAST_NEWS_COMMIT:0:9} (utolsó hírfrissítés) óta történt változások elemzése Claude-dal..." >&2

NEWS_BLOCK="$(printf '%s\n' "$CONTEXT" | claude -p "$INSTRUCTIONS")"
NEWS_BLOCK="$(printf '%s' "$NEWS_BLOCK" | sed -e 's/[[:space:]]*$//' | sed -e '/./,$!d')"

if [ -z "$NEWS_BLOCK" ] || printf '%s' "$NEWS_BLOCK" | grep -q 'NO_USER_FACING_CHANGES'; then
  echo "Claude nem talált felhasználót érintő változást. Nincs teendő."
  exit 0
fi

echo "" >&2
echo "----- Javasolt hírblokk -----" >&2
printf '%s\n' "$NEWS_BLOCK" >&2
echo "-----------------------------" >&2

if [ "$DRY_RUN" -eq 1 ]; then
  echo "(--dry-run: a fájl nem módosult.)" >&2
  exit 0
fi

# A blokk beszúrása közvetlenül az első meglévő dátumbejegyzés elé
# (azaz a "## Hírek" cím alá, legfelülre).
TMP_BLOCK="$(mktemp)"
TMP_OUT="$(mktemp)"
trap 'rm -f "$TMP_BLOCK" "$TMP_OUT"' EXIT
printf '%s\n\n' "$NEWS_BLOCK" > "$TMP_BLOCK"

awk -v blockfile="$TMP_BLOCK" '
  BEGIN { inserted = 0 }
  /^\*\*[0-9]/ && !inserted {
    while ((getline line < blockfile) > 0) { print line }
    close(blockfile)
    inserted = 1
  }
  { print }
  END {
    if (!inserted) {
      print "HIBA: nem található beszúrási pont (dátumbejegyzés) a news.twig-ben." > "/dev/stderr"
      exit 3
    }
  }
' "$NEWS_FILE" > "$TMP_OUT"

cp "$TMP_OUT" "$NEWS_FILE"
echo "» $NEWS_FILE frissítve." >&2

if [ "$NO_COMMIT" -eq 1 ]; then
  echo "(--no-commit: a változás nincs commitolva.)" >&2
  exit 0
fi

git add "$NEWS_FILE"
git commit -m "Update news" >/dev/null
echo "» Commit elkészült: $(git rev-parse --short HEAD) Update news" >&2
