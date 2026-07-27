#!/usr/bin/env bash
#
# Görög fejezetelemzés driver — a paraméterként megadott könyvekre.
#
# Az állapotot a validátor és a tárolt szemantikai rész-eredmények hordozzák.
# A driver kompakt, szószám alapján darabolt bemenetet exportál, darabonként
# egyetlen eszköz nélküli claude hívást futtat, majd helyben állítja össze és
# validálja a teljes fejezetet.
#
# Használat (a repó gyökeréből, /var/www/html):
#   bash bible_import/verse-analysis/gorog-elemzes-driver.sh 1Tim 2Tim
#   bash bible_import/verse-analysis/gorog-elemzes-driver.sh Jn
#
set -euo pipefail

cd /var/www/html

if [ "$#" -eq 0 ]; then
  echo "Használat: $0 <könyv> [könyv ...]" >&2
  echo "Példa:     $0 1Tim 2Tim" >&2
  exit 1
fi

BOOKS=("$@")

VIEWER="bible_import/verse-analysis/stream-view.py"
LOG_DIR="bible_import/verse-analysis/logs"
WORK_ROOT="storage/app/verse-analysis"
PROMPT_FILE="resources/prompts/greek_verse_analysis_semantic.md"
SCHEMA_FILE="resources/prompts/greek_verse_analysis_semantic_schema.json"
MAX_API_ATTEMPTS=5
MAX_SEMANTIC_ROUNDS=3
RETRYABLE_API_ERROR_EXIT_CODE=75
SESSION_LIMIT_EXIT_CODE=76
VERSE_ANALYSIS_MODEL="${VERSE_ANALYSIS_MODEL:-opus}"
VERSE_ANALYSIS_EFFORT="${VERSE_ANALYSIS_EFFORT:-medium}"
VERSE_ANALYSIS_CHUNK_WORDS="${VERSE_ANALYSIS_CHUNK_WORDS:-180}"
VERSE_ANALYSIS_CREATED_BY="${VERSE_ANALYSIS_CREATED_BY:-claude-${VERSE_ANALYSIS_MODEL}}"
analysis_prompt="$(<"$PROMPT_FILE")"
semantic_schema="$(
  php -r '
    $schema = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  ' "$SCHEMA_FILE"
)"
mkdir -p "$LOG_DIR" "$WORK_ROOT"

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

    if php artisan szentiras:validate-verse-analysis "$chapter_reference" >/dev/null 2>&1; then
      echo ">>> [$chapter_position/$chapter_count] $chapter_reference már kész."
      continue
    fi

    semantic_round=1
    while [ "$semantic_round" -le "$MAX_SEMANTIC_ROUNDS" ]; do
      echo ">>> [$chapter_position/$chapter_count] $chapter_reference exportálása \
(${VERSE_ANALYSIS_CHUNK_WORDS} szó/darab)."

      php artisan szentiras:export-verse-analysis-context "$chapter_reference" \
        --dir="$WORK_ROOT" \
        --chunk-words="$VERSE_ANALYSIS_CHUNK_WORDS"

      work_dir="$WORK_ROOT/$chapter_key"
      manifest_file="$work_dir/manifest.json"
      chunk_rows_output="$(
        php -r '
          $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
          foreach ($manifest["chunks"] as $chunk) {
              echo implode("\t", [$chunk["id"], $chunk["reference"], $chunk["source"], $chunk["semantic"]]), PHP_EOL;
          }
        ' "$manifest_file"
      )"
      chunk_rows=()
      if [ -n "$chunk_rows_output" ]; then
        mapfile -t chunk_rows <<< "$chunk_rows_output"
      fi
      chunk_count="${#chunk_rows[@]}"

      for chunk_index in "${!chunk_rows[@]}"; do
        IFS=$'\t' read -r chunk_id chunk_reference source_file semantic_file \
          <<< "${chunk_rows[$chunk_index]}"
        source_path="$work_dir/$source_file"
        semantic_path="$work_dir/$semantic_file"
        chunk_position=$((chunk_index + 1))

        if [ -s "$semantic_path" ]; then
          echo ">>> [$chapter_position/$chapter_count][$chunk_position/$chunk_count] \
$chunk_reference kész rész-eredményből."
          continue
        fi

        attempt=1
        session_limit_count=0
        run_started_at="$(date '+%Y%m%d-%H%M%S')"
        log_file="$LOG_DIR/${chapter_key}-chunk-${chunk_id}.jsonl"

        while true; do
          echo ">>> [$chapter_position/$chapter_count][$chunk_position/$chunk_count] \
$chunk_reference indul ($attempt/$MAX_API_ATTEMPTS. kísérlet, $(date '+%H:%M:%S'))"

          if claude -p "$analysis_prompt" \
            --safe-mode \
            --tools "" \
            --model "$VERSE_ANALYSIS_MODEL" \
            --effort "$VERSE_ANALYSIS_EFFORT" \
            --no-session-persistence \
            --disable-slash-commands \
            --json-schema "$semantic_schema" \
            --output-format stream-json \
            --verbose \
            < "$source_path" \
            | tee "$log_file" \
            | python3 "$VIEWER" --structured-output "$semantic_path"; then
            break
          else
            session_status=$?
          fi

          if [ "$session_status" -eq "$SESSION_LIMIT_EXIT_CODE" ]; then
            session_limit_count=$((session_limit_count + 1))
            failed_log="$LOG_DIR/${chapter_key}-chunk-${chunk_id}-${run_started_at}-session-limit-${session_limit_count}.jsonl"
            mv "$log_file" "$failed_log"

            if ! retry_delay="$(python3 "$VIEWER" --session-reset-delay "$failed_log")"; then
              echo ">>> $chunk_reference: a munkamenet-korlát visszaállítási ideje nem olvasható." >&2
              echo ">>> Hibás futás naplója: $failed_log" >&2
              exit "$SESSION_LIMIT_EXIT_CODE"
            fi

            retry_at="$(date -u --date="+${retry_delay} seconds" '+%Y-%m-%d %H:%M:%S UTC')"
            echo ">>> Munkamenet-korlát. Automatikus folytatás: $retry_at \
(${retry_delay} másodperc múlva)."
            sleep "$retry_delay"
            continue
          fi

          failed_log="$LOG_DIR/${chapter_key}-chunk-${chunk_id}-${run_started_at}-attempt-${attempt}.jsonl"
          mv "$log_file" "$failed_log"

          if [ "$session_status" -ne "$RETRYABLE_API_ERROR_EXIT_CODE" ]; then
            echo ">>> $chunk_reference feldolgozása sikertelen (kilépési kód: $session_status)." >&2
            echo ">>> Hibás futás naplója: $failed_log" >&2
            exit "$session_status"
          fi

          if [ "$attempt" -ge "$MAX_API_ATTEMPTS" ]; then
            echo ">>> $chunk_reference: az API $MAX_API_ATTEMPTS kísérlet után sem érhető el." >&2
            echo ">>> Utolsó hibás futás naplója: $failed_log" >&2
            exit "$RETRYABLE_API_ERROR_EXIT_CODE"
          fi

          retry_delay=$((5 * 2 ** (attempt - 1)))
          echo ">>> Átmeneti API hiba. Újrapróbálás ${retry_delay} másodperc múlva."
          sleep "$retry_delay"
          attempt=$((attempt + 1))
        done
      done

      if php artisan szentiras:assemble-verse-analysis "$manifest_file" \
        --created-by="$VERSE_ANALYSIS_CREATED_BY" \
        && php artisan szentiras:validate-verse-analysis "$chapter_reference"; then
        break
      fi

      if [ "$semantic_round" -ge "$MAX_SEMANTIC_ROUNDS" ]; then
        echo ">>> $chapter_reference: a szemantikai rész-eredmények \
$MAX_SEMANTIC_ROUNDS kör után sem állíthatók össze." >&2
        exit 1
      fi

      semantic_round=$((semantic_round + 1))
      echo ">>> $chapter_reference: hibás rész-eredmény újragenerálása \
($semantic_round/$MAX_SEMANTIC_ROUNDS. kör)."
    done

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
