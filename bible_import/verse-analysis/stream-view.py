#!/usr/bin/env python3
"""
Élő nézet a `claude -p --output-format stream-json` folyamához.

A nyers JSON eseményeket olvasható sorokká alakítja, hogy futás közben
látszódjon, mit csinál az adott elemzési hívás.

Használat:
    claude -p "..." --output-format stream-json --verbose \
      | python3 bible_import/verse-analysis/stream-view.py

Strukturált kimenet mentése:
    claude -p "..." --json-schema '{...}' --output-format stream-json --verbose \
      | python3 bible_import/verse-analysis/stream-view.py \
          --structured-output storage/app/private/greek/verse-analysis/work/semantic.json
"""
import argparse
import json
import math
import re
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Iterator

# Agent tool_use_id -> rövid címke, hogy a sub-agent sorai beazonosíthatók legyenek.
labels: dict[str, str] = {}

API_ERROR_PATTERN = re.compile(r"^API Error:\s*(?P<status>\d{3})\b", re.IGNORECASE)
RETRYABLE_CONNECTION_ERROR_PATTERN = re.compile(
    r"^API Error:\s*Unable to connect to API\b",
    re.IGNORECASE,
)
USAGE_LIMIT_PATTERN = re.compile(
    r"You(?:'|’)ve hit your (?:session|weekly) limit\s*[·•-]\s*resets\s*"
    r"(?P<hour>\d{1,2})(?::(?P<minute>\d{2}))?\s*(?P<meridiem>am|pm)"
    r"\s*\(UTC\)",
    re.IGNORECASE,
)
RETRYABLE_API_STATUSES = {429, 500, 502, 503, 504, 529}
RETRYABLE_API_ERROR_EXIT_CODE = 75
SESSION_LIMIT_EXIT_CODE = 76
SESSION_RESET_BUFFER_SECONDS = 15
SESSION_RESET_GRACE_SECONDS = 300


def short(text: object, limit: int = 200) -> str:
    collapsed = " ".join(str(text).split())
    return collapsed if len(collapsed) <= limit else collapsed[:limit] + "…"


def api_error_status(text: str) -> int | None:
    match = API_ERROR_PATTERN.match(text)

    return int(match.group("status")) if match else None


def session_reset_delay(text: str, now: datetime) -> int | None:
    match = USAGE_LIMIT_PATTERN.search(text)
    if match is None:
        return None

    hour = int(match.group("hour")) % 12
    if match.group("meridiem").lower() == "pm":
        hour += 12

    reset_at = now.replace(
        hour=hour,
        minute=int(match.group("minute") or 0),
        second=0,
        microsecond=0,
    )
    if reset_at <= now:
        if (now - reset_at).total_seconds() <= SESSION_RESET_GRACE_SECONDS:
            reset_at = now
        else:
            reset_at += timedelta(days=1)

    return math.ceil((reset_at - now).total_seconds()) + SESSION_RESET_BUFFER_SECONDS


def timestamp_reset_delay(value: object, now: datetime) -> int | None:
    if isinstance(value, bool) or not isinstance(value, (int, float)):
        return None

    timestamp = float(value)
    if timestamp > 10_000_000_000:
        timestamp /= 1000

    try:
        reset_at = datetime.fromtimestamp(timestamp, timezone.utc)
    except (OverflowError, OSError, ValueError):
        return None

    return (
        max(0, math.ceil((reset_at - now).total_seconds()))
        + SESSION_RESET_BUFFER_SECONDS
    )


def nested_strings(value: object) -> Iterator[str]:
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for item in value.values():
            yield from nested_strings(item)
    elif isinstance(value, list):
        for item in value:
            yield from nested_strings(item)


def reset_delay_from_log(path: Path, now: datetime) -> int | None:
    text_delay = None

    for line in path.read_text(encoding="utf-8").splitlines():
        try:
            event = json.loads(line)
        except json.JSONDecodeError:
            continue

        if event.get("type") == "rate_limit_event":
            rate_limit_info = event.get("rate_limit_info", {})
            if isinstance(rate_limit_info, dict):
                delay = timestamp_reset_delay(rate_limit_info.get("resetsAt"), now)
                if delay is not None:
                    return delay

        for text in nested_strings(event):
            delay = session_reset_delay(text, now)
            if delay is not None and text_delay is None:
                text_delay = delay

    return text_delay


def write_structured_output(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary_path = path.with_name(path.name + ".tmp")
    temporary_path.write_text(
        json.dumps(value, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    temporary_path.replace(path)


def main(structured_output_path: Path | None = None) -> int:
    api_error_detected = False
    retryable_api_error_detected = False
    session_limit_detected = False
    structured_output_written = False

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            event = json.loads(line)
        except json.JSONDecodeError:
            continue

        etype = event.get("type")
        parent = event.get("parent_tool_use_id")
        indent = "    │ " if parent else ""
        tag = ""
        if parent and parent in labels:
            tag = f"[{labels[parent]}] "

        if etype == "rate_limit_event":
            rate_limit_info = event.get("rate_limit_info", {})
            if (
                isinstance(rate_limit_info, dict)
                and rate_limit_info.get("status") == "rejected"
            ):
                api_error_detected = True
                session_limit_detected = True

        elif etype == "system" and event.get("subtype") == "init":
            print(f"── session start ({event.get('model', '')}) ──", flush=True)

        elif etype == "assistant":
            for chunk in event.get("message", {}).get("content", []):
                ctype = chunk.get("type")
                if ctype == "text":
                    text = chunk.get("text", "").strip()
                    if text:
                        status = api_error_status(text)
                        if USAGE_LIMIT_PATTERN.search(text):
                            api_error_detected = True
                            session_limit_detected = True
                            print(f"{indent}⏳ {tag}{short(text)}", flush=True)
                        elif (
                            status is not None
                            or event.get("is_api_error_message") is True
                        ):
                            api_error_detected = True
                            retryable_api_error_detected = (
                                retryable_api_error_detected
                                or status in RETRYABLE_API_STATUSES
                                or RETRYABLE_CONNECTION_ERROR_PATTERN.search(text)
                                is not None
                            )
                            print(f"{indent}⚠️  {tag}{short(text)}", flush=True)
                        else:
                            print(f"{indent}💬 {tag}{short(text)}", flush=True)
                elif ctype == "thinking":
                    thinking = chunk.get("thinking", "").strip()
                    if thinking:
                        print(f"{indent}🤔 {tag}{short(thinking, 160)}", flush=True)
                elif ctype == "tool_use":
                    name = chunk.get("name", "?")
                    inp = chunk.get("input", {}) or {}
                    if name in ("Agent", "Task"):
                        desc = inp.get("description") or inp.get("subagent_type") or "agent"
                        labels[chunk.get("id")] = desc
                        print(f"{indent}🚀 {tag}indít ügynök: {desc}", flush=True)
                    else:
                        detail = (
                            inp.get("file_path")
                            or inp.get("command")
                            or inp.get("pattern")
                            or inp.get("description")
                            or ""
                        )
                        print(f"{indent}🔧 {tag}{name} {short(detail, 100)}", flush=True)

        elif etype == "user":
            for chunk in event.get("message", {}).get("content", []):
                if chunk.get("type") == "tool_result" and chunk.get("is_error"):
                    print(f"{indent}⚠️  {tag}tool hiba", flush=True)

        elif etype == "result":
            cost = event.get("total_cost_usd")
            extra = f"  (${cost:.2f})" if isinstance(cost, (int, float)) else ""
            usage = event.get("usage", {})
            if isinstance(usage, dict):
                cache_created = usage.get("cache_creation_input_tokens")
                cache_read = usage.get("cache_read_input_tokens")
                output = usage.get("output_tokens")
                if all(isinstance(value, int) for value in (cache_created, cache_read, output)):
                    extra += (
                        f"  [cache-create={cache_created}, "
                        f"cache-read={cache_read}, output={output}]"
                    )

            if api_error_detected:
                subtype = event.get("subtype", "done")
                print(
                    f"❌ result: API hiba (jelzett altípus: {subtype}){extra}",
                    flush=True,
                )
            else:
                print(
                    f"✅ result: {event.get('subtype', 'done')}{extra}",
                    flush=True,
                )
                structured_output = event.get("structured_output")
                if (
                    structured_output_path is not None
                    and event.get("subtype") == "success"
                    and isinstance(structured_output, dict)
                ):
                    write_structured_output(
                        structured_output_path,
                        structured_output,
                    )
                    structured_output_written = True

    if session_limit_detected:
        return SESSION_LIMIT_EXIT_CODE

    if retryable_api_error_detected:
        return RETRYABLE_API_ERROR_EXIT_CODE

    if structured_output_path is not None and not structured_output_written:
        return 1

    return 1 if api_error_detected else 0


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--session-reset-delay", type=Path)
    parser.add_argument("--structured-output", type=Path)
    parser.add_argument("--now", type=datetime.fromisoformat)

    return parser.parse_args()


if __name__ == "__main__":
    try:
        arguments = parse_arguments()
        if arguments.session_reset_delay is None:
            raise SystemExit(main(arguments.structured_output))

        current_time = arguments.now or datetime.now(timezone.utc)
        if current_time.tzinfo is None:
            current_time = current_time.replace(tzinfo=timezone.utc)
        else:
            current_time = current_time.astimezone(timezone.utc)

        reset_delay = reset_delay_from_log(
            arguments.session_reset_delay,
            current_time,
        )
        if reset_delay is None:
            raise SystemExit(1)

        print(reset_delay)
    except (BrokenPipeError, KeyboardInterrupt):
        pass
