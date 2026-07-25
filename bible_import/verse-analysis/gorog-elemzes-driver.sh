#!/usr/bin/env bash
#
# Görög fejezetelemzés driver — a paraméterként megadott könyvekre.
#
# Az állapotot a fájlok jelenléte + a validátor hordozza, nem a beszélgetés,
# ezért ez a ciklus bármikor megszakítható és újraindítható: a --missing
# mindig onnan folytatja, ahol tart.
#
# Használat (a repó gyökeréből, /var/www/html):
#   bash bible_import/verse-analysis/gorog-elemzes-driver.sh 1Tim 2Tim
#   bash bible_import/verse-analysis/gorog-elemzes-driver.sh Jn
#
# FIGYELEM: a --permission-mode bypassPermissions MINDEN jóváhagyást kikapcsol
# az adott claude futásban, nem csak ezekét. Dedikált munkamenetben futtasd.

set -euo pipefail

cd /var/www/html

if [ "$#" -eq 0 ]; then
  echo "Használat: $0 <könyv> [könyv ...]" >&2
  echo "Példa:     $0 1Tim 2Tim" >&2
  exit 1
fi

BOOKS=("$@")
MAX_ROUNDS=8   # könyvenkénti biztonsági plafon, hogy ne pörögjön végtelenül

# Élő nézet: a claude stream-json folyamát emberi olvasásra formázzuk, közben
# a nyers eseményeket körönként logfájlba is mentjük későbbi visszanézéshez.
VIEWER="bible_import/verse-analysis/stream-view.py"
LOG_DIR="bible_import/verse-analysis/logs"
mkdir -p "$LOG_DIR"

for book in "${BOOKS[@]}"; do
  echo "===================================================================="
  echo "  KÖNYV: $book"
  echo "===================================================================="

  round=0
  # A --missing --json egy JSON tömböt ad a hiányzó fejezetekről: üres könyvnél
  # "[]". A sima --missing MINDIG kiír egy összegző sort ("0 chapter(s) missing…"),
  # ezért arra a "grep -q ." sosem áll le — kész könyvön is elpörögne a plafonig.
  while missing_json="$(php artisan szentiras:validate-verse-analysis "$book" --missing --json)" \
    && [ "$missing_json" != "[]" ]; do
    round=$((round + 1))
    if [ "$round" -gt "$MAX_ROUNDS" ]; then
      echo ">>> $book: elértük a $MAX_ROUNDS körös plafont, még maradt hiányzó fejezet."
      echo ">>> Nézd meg kézzel: php artisan szentiras:validate-verse-analysis $book --missing"
      break
    fi

    echo ">>> $book — $round. kör indul ($(date '+%H:%M:%S'))"

    read -r missing_count first_missing_key < <(
      php -r '$missing = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo count($missing)." ".($missing[0] ?? "").PHP_EOL;' "$missing_json"
    )

    if [ "$missing_count" -eq 1 ]; then
      chapter_number="${first_missing_key##*_}"
      chapter_reference="$book $chapter_number"

      claude -p "Dolgozd fel és validáld a(z) $chapter_reference fejezetet a gorog-elemzo \
ügynök utasításai szerint. Csak ezt az egy fejezetet dolgozd fel, más fájlt ne módosíts." \
        --agent gorog-elemzo \
        --permission-mode bypassPermissions \
        --output-format stream-json \
        --verbose \
        | tee "$LOG_DIR/${book}-r${round}.jsonl" \
        | python3 "$VIEWER"
    else
      claude -p "Olvasd be a .claude/skills/gorog-elemzes/reference/orchestration.md fájlt, \
és aszerint dolgozz. A(z) $book könyv hiányzó fejezeteit dolgozd fel: \
futtasd a 'php artisan szentiras:validate-verse-analysis $book --missing' parancsot, \
majd a hiányzó fejezetekre 3-4 párhuzamos gorog-elemzo ügynökkel fan-out (Agent tool, \
subagent_type: gorog-elemzo), ügynökönként PONTOSAN egy fejezet. Minden kör után \
validátor-kapu ('php artisan szentiras:validate-verse-analysis $book'); ami piros, azt \
az egy fejezetet futtasd újra. Kész, zölden futó fejezetet SOHA ne generáltass újra. \
Csak a(z) $book könyv hiányzó fejezeteivel foglalkozz, más fájlt ne módosíts." \
      --permission-mode bypassPermissions \
      --output-format stream-json \
      --verbose \
      --forward-subagent-text \
      | tee "$LOG_DIR/${book}-r${round}.jsonl" \
      | python3 "$VIEWER"
    fi

    echo ">>> $book — $round. kör vége ($(date '+%H:%M:%S'))"
  done

  echo ">>> $book KÉSZ — nincs több hiányzó fejezet."
done

echo "===================================================================="
echo "  Kész. Záró állapot:"
echo "===================================================================="
for book in "${BOOKS[@]}"; do
  php artisan szentiras:validate-verse-analysis "$book" || true
done
