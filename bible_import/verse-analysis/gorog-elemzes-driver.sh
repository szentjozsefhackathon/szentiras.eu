#!/usr/bin/env bash
#
# Görög fejezetelemzés driver — a paraméterként megadott könyvekre.
#
# Az állapotot a validátor hordozza, nem a beszélgetés. A driver a könyv minden
# hiányzó vagy hibás fejezetét önálló claude folyamatban dolgozza fel, átmeneti
# API-hiba esetén korlátozott újrapróbálással, a validátor kanonikus sorrendjében.
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

# Élő nézet: a claude stream-json folyamát emberi olvasásra formázzuk, közben a
# nyers eseményeket fejezetenként logfájlba is mentjük későbbi visszanézéshez.
VIEWER="bible_import/verse-analysis/stream-view.py"
LOG_DIR="bible_import/verse-analysis/logs"
MAX_API_ATTEMPTS=5
RETRYABLE_API_ERROR_EXIT_CODE=75
SESSION_LIMIT_EXIT_CODE=76
mkdir -p "$LOG_DIR"

for book in "${BOOKS[@]}"; do
  echo "===================================================================="
  echo "  KÖNYV: $book"
  echo "===================================================================="

  validation_json=""
  if ! validation_json="$(php artisan szentiras:validate-verse-analysis "$book" --json)"; then
    true
  fi

  chapter_keys_output="$(
    php -r '
      $results = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
      foreach ($results as $result) {
          if (($result["errors"] ?? []) !== []) {
              echo $result["chapter"], PHP_EOL;
          }
      }
    ' "$validation_json"
  )"

  chapter_keys=()
  if [ -n "$chapter_keys_output" ]; then
    mapfile -t chapter_keys <<< "$chapter_keys_output"
  fi

  chapter_count="${#chapter_keys[@]}"
  if [ "$chapter_count" -eq 0 ]; then
    echo ">>> $book KÉSZ — minden fejezet valid."
    continue
  fi

  echo ">>> $book: $chapter_count feldolgozandó fejezet."

  for chapter_index in "${!chapter_keys[@]}"; do
    chapter_key="${chapter_keys[$chapter_index]}"
    chapter_number="${chapter_key##*_}"
    chapter_reference="$book $chapter_number"
    chapter_position=$((chapter_index + 1))

    attempt=1
    session_limit_count=0
    run_started_at="$(date '+%Y%m%d-%H%M%S')"
    log_file="$LOG_DIR/${chapter_key}.jsonl"

    while true; do
      echo ">>> [$chapter_position/$chapter_count] $chapter_reference indul \
($attempt/$MAX_API_ATTEMPTS. kísérlet, $(date '+%H:%M:%S'))"

      if claude -p "Dolgozd fel és validáld a(z) $chapter_reference fejezetet a gorog-elemzo \
ügynök utasításai szerint. Csak ezt az egy fejezetet dolgozd fel, más fájlt ne módosíts." \
        --agent gorog-elemzo \
        --permission-mode bypassPermissions \
        --output-format stream-json \
        --verbose \
        | tee "$log_file" \
        | python3 "$VIEWER"; then
        break
      else
        session_status=$?
      fi

      if [ "$session_status" -eq "$SESSION_LIMIT_EXIT_CODE" ]; then
        session_limit_count=$((session_limit_count + 1))
        failed_log="$LOG_DIR/${chapter_key}-${run_started_at}-session-limit-${session_limit_count}.jsonl"
        mv "$log_file" "$failed_log"

        if ! retry_delay="$(python3 "$VIEWER" --session-reset-delay "$failed_log")"; then
          echo ">>> $chapter_reference: a munkamenet-korlát visszaállítási ideje nem olvasható." >&2
          echo ">>> Hibás futás naplója: $failed_log" >&2
          exit "$SESSION_LIMIT_EXIT_CODE"
        fi

        retry_at="$(date -u --date="+${retry_delay} seconds" '+%Y-%m-%d %H:%M:%S UTC')"
        echo ">>> Munkamenet-korlát. Automatikus folytatás: $retry_at \
(${retry_delay} másodperc múlva)."
        sleep "$retry_delay"
        continue
      fi

      if [ "$session_status" -ne "$RETRYABLE_API_ERROR_EXIT_CODE" ]; then
        echo ">>> $chapter_reference feldolgozása sikertelen (kilépési kód: $session_status)." >&2
        exit "$session_status"
      fi

      failed_log="$LOG_DIR/${chapter_key}-${run_started_at}-attempt-${attempt}.jsonl"
      mv "$log_file" "$failed_log"

      if [ "$attempt" -ge "$MAX_API_ATTEMPTS" ]; then
        echo ">>> $chapter_reference: az API $MAX_API_ATTEMPTS kísérlet után sem érhető el." >&2
        echo ">>> Utolsó hibás futás naplója: $failed_log" >&2
        exit "$RETRYABLE_API_ERROR_EXIT_CODE"
      fi

      retry_delay=$((5 * 2 ** (attempt - 1)))
      echo ">>> Átmeneti API hiba. Újrapróbálás ${retry_delay} másodperc múlva."
      sleep "$retry_delay"
      attempt=$((attempt + 1))
    done

    php artisan szentiras:validate-verse-analysis "$chapter_reference"

    echo ">>> [$chapter_position/$chapter_count] $chapter_reference kész ($(date '+%H:%M:%S'))"
  done

  php artisan szentiras:validate-verse-analysis "$book"
  echo ">>> $book KÉSZ — minden fejezet valid."
done

echo "===================================================================="
echo "  Kész. Záró állapot:"
echo "===================================================================="
for book in "${BOOKS[@]}"; do
  php artisan szentiras:validate-verse-analysis "$book" || true
done
